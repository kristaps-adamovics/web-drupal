# DE1015 - IoT sensoru projekts


## 1.Datu attēlošanas pārskata sadaļa:
Nepieciešams nodrošināt iespēju datus par telpu rādītājiem nolasīt gan tabulārā, gan grafiskā veidā ar iespēju veikt filtrēšanu, piemēram, pēc laika perioda.

Izveidota Drupal moduļa sadaļa `Telpu rādītāju pārskats`:

- adrese: `/iot-sensors/reports`;
- dati redzami tabulā un grafikā;
- iespējams filtrēt pēc perioda, telpas un rādītāja;
- modulim ir sākotnējie demo dati telpām, sensoriem un mērījumiem.

## 2. Ēkas plāns un sensori
Nepieciešams izveidot sadaļu, kurā redzams ēkas plāns, telpu saraksts un katras telpas atbilstošie sensori. Jānodrošina sensora pārskata atvēršana no ēkas plāna lapas. Ēkas plāna un sensoru lapā ir iespējams pievienot jaunas telpas un mitruma, temperatūras, CO2 sensorus šajās telpās (telpā iespējams pievienot vairākus viena tipa sensorus), katram sensoram un telpai tiek piešķirts unikāls ID.

Izveidota Drupal moduļa sadaļa `Ēkas plāns un sensori`:

- adrese: `/iot-sensors/building`;
- galvenajā izvēlnē redzama poga `Ēkas plāns`;
- redzams vienkāršs ēkas plāns ar telpām un sensoriem;
- redzams telpu saraksts un sensoru saraksts;
- no sensora iespējams atvērt datu pārskatu;
- iespējams pievienot jaunas telpas;
- iespējams pievienot temperatūras, mitruma un CO2 sensorus telpām;
- iespējams labot un dzēst telpas un sensorus.

## 3. Brīdinājumi
Iespējams katrai telpai un sensoram definēt brīdinājumus, norādot robežvērtību (piemēram, temperatūra > 30. Brīdinājumi parādās brīdinājumu pārskata lapā. Brīdinājumus iespējams filtrēt pēc laika, telpas, sensora

Izveidota Drupal moduļa sadaļa `Brīdinājumi`:

- adrese: `/iot-sensors/alerts`;
- galvenajā izvēlnē redzama poga `Brīdinājumi`;
- iespējams pievienot brīdinājuma noteikumu sensoram;
- noteikumam var norādīt robežvērtību, piemēram `>` un `30`;
- brīdinājumu pārskatā redzami mērījumi, kuri pārsniedz noteikumu;
- brīdinājumus iespējams filtrēt pēc laika, telpas un sensora;
- brīdinājuma noteikumus iespējams dzēst.

## 4.Reāllaika informācijas saņemšana
Ēkas sensora datus ir iespējams iesūtīt sistēmas REST tīmekļa servisā, norādot sensora ID un vērtību formātā:

```
{
  "sensor": "100",
  "value": "2000"
}
```

Informācijas iesūtīšanu var pārbaudīt ar Postman vai speciāli izstrādātu skriptu palīdzību. Lai būtu pieejami demo dati, būtu ieteicams importēt vismaz daļu no MS Excel failā esošajiem datiem.

Izveidots REST tīmekļa serviss reāllaika mērījumu saņemšanai:

- adrese: `POST /iot-sensors/api/readings`;
- pieprasījuma tips: `Content-Type: application/json`;
- lauki: `sensor` - sensora ID, `value` - mērījuma vērtība;
- veiksmīga pieprasījuma gadījumā mērījums tiek saglabāts `iot_sensors_readings` tabulā ar pašreizējo laiku;
- jaunais mērījums pēc tam redzams sadaļā `Telpu rādītāju pārskats` un var aktivizēt sadaļas `Brīdinājumi` noteikumus.

Pārbaude ar `curl`:

```bash
curl -X POST http://localhost:8888/iot-sensors/api/readings \
  -H "Content-Type: application/json" \
  -d '{"sensor":"100","value":"2000"}'
```

Pārbaude ar Python skriptu:

```bash
python3 scripts/send_sensor_reading.py 100 2000
```

## Excel datu imports

`IoT POIC.xlsx` datu importu var atkārtot ar komandu:

```bash
python3 scripts/import_iot_poic.py
```

Imports aizvieto `iot_sensors` telpu, sensoru un mērījumu demo datus ar Excel faila `POIC_1` līdz `POIC_5` lapu datiem. Excel datumos izmantotie 2020. gada datumi tiek saglabāti bez pārbīdes uz pašreizējo gadu.

## Kā palaist projektu

```bash
docker compose up -d
```

Pēc tam:

- Drupal: `http://localhost:8888`
- Adminer: `http://localhost:8090`

Datubāzes dati:

- serveris: `mysql`
- datubāze: `drupal`
- lietotājs: `dbuser`
- parole: `drupal-secret`

## Pilna palaišana no nulles

Komandas jāpalaiž projekta saknes mapē, kur atrodas `compose.yaml`.

Uzmanību: pirmā komanda dzēš esošos konteinerus un Docker volumes, tātad tiks dzēsta arī Drupal datubāze.

```bash
docker compose down -v --remove-orphans
docker compose up -d
docker compose ps
```

Pagaidi, līdz `drupal` un `mysql` konteineri ir statusā `Up`. Pēc tam uzinstalē Drush Drupal konteinerī:

```bash
docker exec drupal sh -lc 'cd /opt/drupal && composer require drush/drush --no-interaction'
```

Uzinstalē Drupal datubāzi:

```bash
docker exec drupal sh -lc 'cd /opt/drupal && vendor/bin/drush site:install standard --db-url=mysql://dbuser:drupal-secret@mysql/drupal --site-name="web drupal" --account-name=admin --account-pass=admin -y'
```

Ieslēdz pielāgoto IoT sensoru moduli:

```bash
docker exec drupal sh -lc 'cd /opt/drupal && vendor/bin/drush en iot_sensors -y'
```

Importē Excel datus no `IoT POIC.xlsx`:

```bash
python3 scripts/import_iot_poic.py
```

Notīri Drupal kešu:

```bash
docker exec drupal sh -lc 'cd /opt/drupal && vendor/bin/drush cr'
```

Pēc tam atver:

- Drupal: `http://localhost:8888`
- Telpu rādītāju pārskats: `http://localhost:8888/iot-sensors/reports`
- Ēkas plāns un sensori: `http://localhost:8888/iot-sensors/building`
- Brīdinājumi: `http://localhost:8888/iot-sensors/alerts`
- Adminer: `http://localhost:8090`

Drupal admin lietotājs pēc šīs instalācijas:

- lietotājs: `admin`
- parole: `admin`
