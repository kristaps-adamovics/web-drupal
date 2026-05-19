#!/usr/bin/env python3
"""Send one IoT sensor reading to the Drupal REST endpoint."""

import argparse
import json
import sys
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


def main() -> int:
    parser = argparse.ArgumentParser(description="Send a sensor reading to Drupal.")
    parser.add_argument("sensor", help="Numeric sensor ID, for example 100")
    parser.add_argument("value", help="Numeric sensor value, for example 2000")
    parser.add_argument(
        "--url",
        default="http://localhost:8888/iot-sensors/api/readings",
        help="Endpoint URL.",
    )
    args = parser.parse_args()

    payload = json.dumps({"sensor": args.sensor, "value": args.value}).encode("utf-8")
    request = Request(
        args.url,
        data=payload,
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        method="POST",
    )

    try:
        with urlopen(request, timeout=15) as response:
            body = response.read().decode("utf-8")
            print(body)
            return 0 if 200 <= response.status < 300 else 1
    except HTTPError as error:
        print(error.read().decode("utf-8"), file=sys.stderr)
        return 1
    except URLError as error:
        print(f"Request failed: {error.reason}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
