#!/usr/bin/env python3
"""Import POIC room sensor readings from IoT POIC.xlsx into the Drupal DB.

The script uses only Python's standard library, so it can run without adding
Excel parser dependencies to the project.
"""

from __future__ import annotations

import argparse
import subprocess
import sys
import zipfile
import xml.etree.ElementTree as ET
from datetime import datetime, timedelta, timezone
from pathlib import Path


MAIN_NS = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
REL_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
PKG_REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
NS = {"m": MAIN_NS, "r": REL_NS, "pr": PKG_REL_NS}

METRICS = {
    "co2": ("co2", "CO2", "ppm"),
    "temperature": ("temperature", "TMP", "C"),
    "humidity": ("humidity", "HUM", "%"),
}

ROOM_NAMES = {
    "Servertelpa": "Servertelpa",
    "Videonov\u0113ro\u0161anas telpa": "Videonovērošanas telpa",
    "14. telpa": "14. telpa",
    "Dispe\u010deru telpa": "Dispečeru telpa",
    "13. telpa": "13. telpa",
}


def load_shared_strings(workbook: zipfile.ZipFile) -> list[str]:
    if "xl/sharedStrings.xml" not in workbook.namelist():
        return []

    root = ET.fromstring(workbook.read("xl/sharedStrings.xml"))
    values: list[str] = []
    for item in root.findall("m:si", NS):
        values.append("".join(text.text or "" for text in item.findall(".//m:t", NS)))
    return values


def cell_value(cell: ET.Element, shared_strings: list[str]) -> str:
    value = cell.find("m:v", NS)
    if value is None or value.text is None:
        return ""

    if cell.attrib.get("t") == "s":
        return shared_strings[int(value.text)]

    return value.text


def load_sheet_paths(workbook: zipfile.ZipFile) -> dict[str, str]:
    root = ET.fromstring(workbook.read("xl/workbook.xml"))
    relationships = ET.fromstring(workbook.read("xl/_rels/workbook.xml.rels"))
    rel_map = {
        rel.attrib["Id"]: rel.attrib["Target"]
        for rel in relationships.findall("pr:Relationship", NS)
    }

    paths: dict[str, str] = {}
    for sheet in root.findall("m:sheets/m:sheet", NS):
        rel_id = sheet.attrib[f"{{{REL_NS}}}id"]
        target = rel_map[rel_id].lstrip("/")
        paths[sheet.attrib["name"]] = target if target.startswith("xl/") else f"xl/{target}"

    return paths


def excel_timestamp(date_serial: str, time_serial: str) -> int | None:
    if not date_serial or not time_serial:
        return None

    try:
        serial = float(date_serial) + float(time_serial)
    except ValueError:
        return None

    dt = datetime(1899, 12, 30, tzinfo=timezone.utc) + timedelta(days=serial)
    return int(dt.timestamp())


def normalized_room_name(raw: str) -> str:
    name = raw.split(",")[0].strip() or raw.strip()
    return ROOM_NAMES.get(name, name)


def parse_poic_rows(path: Path) -> tuple[list[dict[str, str]], list[dict[str, str]], list[dict[str, str]]]:
    rooms: list[dict[str, str]] = []
    sensors: list[dict[str, str]] = []
    readings: list[dict[str, str]] = []

    with zipfile.ZipFile(path) as workbook:
        shared_strings = load_shared_strings(workbook)
        sheet_paths = load_sheet_paths(workbook)

        for sheet_name in sorted(name for name in sheet_paths if name.startswith("POIC_")):
            root = ET.fromstring(workbook.read(sheet_paths[sheet_name]))
            rows = root.findall("m:sheetData/m:row", NS)
            if len(rows) < 3:
                continue

            first_row = [cell_value(cell, shared_strings) for cell in rows[0].findall("m:c", NS)]
            room_name = normalized_room_name(first_row[9] if len(first_row) > 9 else sheet_name)
            room_key = sheet_name.lower().replace("_", "-")
            rooms.append({"key": room_key, "name": room_name, "floor": ""})

            sheet_sensors: list[tuple[str, int, str]] = []
            for label, start_col in [("co2", 0), ("temperature", 3), ("humidity", 6)]:
                metric, prefix, unit = METRICS[label]
                sensor_code = f"{prefix}-{sheet_name.replace('_', '-')}"
                sensor_key = f"{room_key}-{metric}"
                sensors.append({
                    "key": sensor_key,
                    "room_key": room_key,
                    "sensor_code": sensor_code,
                    "metric": metric,
                    "unit": unit,
                })
                sheet_sensors.append((sensor_key, start_col, metric))

            for row in rows[2:]:
                values = [cell_value(cell, shared_strings) for cell in row.findall("m:c", NS)]
                for sensor_key, start_col, _metric in sheet_sensors:
                    if len(values) <= start_col + 2:
                        continue

                    created = excel_timestamp(values[start_col], values[start_col + 1])
                    value = values[start_col + 2]
                    if created is None or value == "":
                        continue

                    readings.append({
                        "sensor_key": sensor_key,
                        "value": f"{float(value):.2f}",
                        "created": str(created),
                    })

    return rooms, sensors, readings


def run(command: list[str], input_text: str | None = None) -> None:
    subprocess.run(command, check=True, input=input_text, text=input_text is not None)


def sql_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def append_insert_batches(sql_parts: list[str], table: str, columns: list[str], rows: list[dict[str, str]], batch_size: int = 500) -> None:
    column_list = ", ".join(f"`{column}`" for column in columns)
    for start in range(0, len(rows), batch_size):
        values = []
        for row in rows[start:start + batch_size]:
            values.append("(" + ", ".join(sql_string(row[column]) for column in columns) + ")")
        sql_parts.append(f"INSERT INTO {table} ({column_list}) VALUES\n" + ",\n".join(values) + ";")


def import_to_docker_mysql(workbook_path: Path, mysql_container: str, database: str, user: str, password: str) -> None:
    rooms, sensors, readings = parse_poic_rows(workbook_path)
    if not rooms or not sensors or not readings:
        raise RuntimeError("No POIC room, sensor, or reading rows were found in the workbook.")

    sql_parts = ["""
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DROP TEMPORARY TABLE IF EXISTS tmp_iot_rooms;
DROP TEMPORARY TABLE IF EXISTS tmp_iot_sensors;
DROP TEMPORARY TABLE IF EXISTS tmp_iot_readings;
CREATE TEMPORARY TABLE tmp_iot_rooms (`key` VARCHAR(64), name VARCHAR(128), floor VARCHAR(32));
CREATE TEMPORARY TABLE tmp_iot_sensors (`key` VARCHAR(64), room_key VARCHAR(64), sensor_code VARCHAR(64), metric VARCHAR(32), unit VARCHAR(16));
CREATE TEMPORARY TABLE tmp_iot_readings (sensor_key VARCHAR(64), value NUMERIC(10,2), created INT UNSIGNED);
"""]
    append_insert_batches(sql_parts, "tmp_iot_rooms", ["key", "name", "floor"], rooms)
    append_insert_batches(sql_parts, "tmp_iot_sensors", ["key", "room_key", "sensor_code", "metric", "unit"], sensors)
    append_insert_batches(sql_parts, "tmp_iot_readings", ["sensor_key", "value", "created"], readings)
    sql_parts.append("""
TRUNCATE TABLE iot_sensors_alert_rules;
TRUNCATE TABLE iot_sensors_readings;
TRUNCATE TABLE iot_sensors_sensors;
TRUNCATE TABLE iot_sensors_rooms;
INSERT INTO iot_sensors_rooms (name, floor)
  SELECT name, floor FROM tmp_iot_rooms ORDER BY `key`;
INSERT INTO iot_sensors_sensors (room_id, sensor_code, metric, unit)
  SELECT rooms.id, sensors.sensor_code, sensors.metric, sensors.unit
  FROM tmp_iot_sensors sensors
  JOIN tmp_iot_rooms tmp_rooms ON tmp_rooms.`key` = sensors.room_key
  JOIN iot_sensors_rooms rooms ON rooms.name = tmp_rooms.name
  ORDER BY sensors.`key`;
INSERT INTO iot_sensors_readings (sensor_id, value, created)
  SELECT sensors.id, readings.value, readings.created
  FROM tmp_iot_readings readings
  JOIN tmp_iot_sensors tmp_sensors ON tmp_sensors.`key` = readings.sensor_key
  JOIN iot_sensors_sensors sensors ON sensors.sensor_code = tmp_sensors.sensor_code
  ORDER BY readings.created;
SET FOREIGN_KEY_CHECKS=1;
SELECT COUNT(*) AS rooms FROM iot_sensors_rooms;
SELECT COUNT(*) AS sensors FROM iot_sensors_sensors;
SELECT COUNT(*) AS readings, FROM_UNIXTIME(MIN(created)) AS min_date, FROM_UNIXTIME(MAX(created)) AS max_date FROM iot_sensors_readings;
""")
    sql = "\n".join(sql_parts)

    run([
        "docker",
        "exec",
        "-i",
        mysql_container,
        "mysql",
        "--default-character-set=utf8mb4",
        f"-u{user}",
        f"-p{password}",
        database,
    ], sql)


def main() -> int:
    parser = argparse.ArgumentParser(description="Import IoT POIC.xlsx into Drupal's iot_sensors tables.")
    parser.add_argument("--xlsx", default="IoT POIC.xlsx", help="Path to the source Excel workbook.")
    parser.add_argument("--mysql-container", default="mysql")
    parser.add_argument("--database", default="drupal")
    parser.add_argument("--user", default="dbuser")
    parser.add_argument("--password", default="drupal-secret")
    args = parser.parse_args()

    workbook_path = Path(args.xlsx)
    if not workbook_path.exists():
        parser.error(f"Workbook not found: {workbook_path}")

    import_to_docker_mysql(workbook_path, args.mysql_container, args.database, args.user, args.password)
    return 0


if __name__ == "__main__":
    sys.exit(main())
