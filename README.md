# DE1015 - IoT sensoru projekts


## 1.Datu attēlošanas pārskata sadaļa:
Nepieciešams nodrošināt iespēju datus par telpu rādītājiem nolasīt gan tabulārā, gan grafiskā veidā ar iespēju veikt filtrēšanu, piemēram, pēc laika perioda.

## 2. Ēkas plāns un sensori
Nepieciešams izveidot sadaļu, kurā redzams ēkas plāns, telpu saraksts un katras telpas atbilstošie sensori. Jānodrošina sensora pārskata atvēršana no ēkas plāna lapas. Ēkas plāna un sensoru lapā ir iespējams pievienot jaunas telpas un mitruma, temperatūras, CO2 sensorus šajās telpās (telpā iespējams pievienot vairākus viena tipa sensorus), katram sensoram un telpai tiek piešķirts unikāls ID.

## 3. Brīdinājumi
Iespējams katrai telpai un sensoram definēt brīdinājumus, norādot robežvērtību (piemēram, temperatūra > 30. Brīdinājumi parādās brīdinājumu pārskata lapā. Brīdinājumus iespējams filtrēt pēc laika, telpas, sensora

## 4.Reāllaika informācijas saņemšana
Ēkas sensora datus ir iespējams iesūtīt sistēmas REST tīmekļa servisā, norādot sensora ID un vērtību formātā:

```
{
  “sensor”: “100”,
  “value”: “2000”
}
```

Informācijas iesūtīšanu var pārbaudīt ar Postman vai speciāli izstrādātu skriptu palīdzību. Lai būtu pieejami demo dati, būtu ieteicams importēt vismaz daļu no MS Excel failā esošajiem datiem.

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
