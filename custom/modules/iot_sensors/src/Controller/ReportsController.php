<?php

namespace Drupal\iot_sensors\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;
use Drupal\iot_sensors\Form\ReportsFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ReportsController extends ControllerBase {

  protected $database;
  protected $dateFormatter;
  protected $requestStack;
  protected $time;
  protected $filterFormBuilder;

  public function __construct(
    Connection $database,
    DateFormatterInterface $dateFormatter,
    RequestStack $requestStack,
    TimeInterface $time,
    FormBuilderInterface $filterFormBuilder
  ) {
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->requestStack = $requestStack;
    $this->time = $time;
    $this->filterFormBuilder = $filterFormBuilder;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('form_builder'),
    );
  }

  public function overview() {
    $filters = $this->getFilters();
    $rows = $this->loadRows($filters);
    $chart = $this->buildChartData($rows);

    return [
      '#cache' => [
        'contexts' => ['url.query_args'],
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => ['iot_sensors/reports'],
        'drupalSettings' => [
          'iotSensorsReport' => [
            'labels' => $chart['labels'],
            'series' => $chart['series'],
          ],
        ],
      ],
      'filter' => $this->buildFilterForm($filters),

      'summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['iot-report-summary']],
        'count' => [
          '#markup' => $this->formatPlural(count($rows), 'Atrasts 1 mērījums.', 'Atrasti @count mērījumi.'),
        ],
      ],

      'chart' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['iot-report-chart']],
        'canvas' => [
          '#type' => 'html_tag',
          '#tag' => 'canvas',
          '#attributes' => [
            'id' => 'iot-sensors-report-chart',
            'height' => 320,
            'aria-label' => $this->t('Telpu rādītāju grafiks'),
            'role' => 'img',
          ],
        ],
        'empty' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['iot-report-empty-chart']],
          '#markup' => $this->t('Nav datu grafiskai attēlošanai.'),
        ],
      ],

      // ==================== UZLABOTĀ TABULA ====================
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Laiks'),
          $this->t('Telpa'),
          $this->t('Sensors'),
          $this->t('Rādītājs'),
          $this->t('Vērtība'),
        ],
        '#rows' => $this->buildTableRows($rows),
        '#empty' => $this->t('Izvēlētajā periodā dati netika atrasti.'),
        '#attributes' => [
          'class' => [
            'table',
            'table-striped',
            'table-hover',
            'table-responsive',
            'iot-report-table',
          ],
        ],
        '#sticky' => TRUE,
        '#caption' => $this->t('Mērījumu saraksts'),
      ],
    ];
  }

  private function getFilters() {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request?->query;
    $default_dates = $this->getDefaultDateRange();

    $date_from = (string) ($query?->get('date_from') ?: $default_dates['date_from']);
    $date_to = (string) ($query?->get('date_to') ?: $default_dates['date_to']);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
      $date_from = $default_dates['date_from'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
      $date_to = $default_dates['date_to'];
    }

    return [
      'date_from' => $date_from,
      'date_to' => $date_to,
      'room_id' => (int) ($query?->get('room_id') ?: 0),
      'metric' => (string) ($query?->get('metric') ?: ''),
      'sensor_id' => (int) ($query?->get('sensor_id') ?: 0),
    ];
  }

  private function getDefaultDateRange(): array {
    $latest = (int) $this->database->select('iot_sensors_readings', 'r')
      ->fields('r', ['created'])
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($latest <= 0) {
      $latest = $this->time->getRequestTime();
    }

    return [
      'date_from' => date('Y-m-d', strtotime('-7 days', $latest)),
      'date_to' => date('Y-m-d', $latest),
    ];
  }

  private function buildFilterForm(array $filters) {
    return $this->filterFormBuilder->getForm(ReportsFilterForm::class, $filters);
  }

  private function loadRows(array $filters) {
    $query = $this->baseQuery();
    $from = strtotime($filters['date_from'] . ' 00:00:00') ?: 0;
    $to = strtotime($filters['date_to'] . ' 23:59:59') ?: $this->time->getRequestTime();

    $query->condition('r.created', $from, '>=');
    $query->condition('r.created', $to, '<=');

    if ($filters['room_id'] > 0) {
      $query->condition('room.id', $filters['room_id']);
    }
    if ($filters['metric'] !== '' && array_key_exists($filters['metric'], $this->metricOptions())) {
      $query->condition('s.metric', $filters['metric']);
    }
    if ($filters['sensor_id'] > 0) {
      $query->condition('s.id', $filters['sensor_id']);
    }

    $query->orderBy('r.created', 'DESC');
    $query->range(0, 200);

    return $query->execute()->fetchAll();
  }

  private function baseQuery(): SelectInterface {
    $query = $this->database->select('iot_sensors_readings', 'r');
    $query->join('iot_sensors_sensors', 's', 's.id = r.sensor_id');
    $query->join('iot_sensors_rooms', 'room', 'room.id = s.room_id');

    $query->fields('r', ['value', 'created']);
    $query->fields('s', ['id', 'sensor_code', 'metric', 'unit']);
    $query->addField('room', 'id', 'room_id');
    $query->addField('room', 'name', 'room_name');

    return $query;
  }

  private function buildTableRows(array $rows) {
    $table_rows = [];

    foreach ($rows as $row) {
      $value = (float) $row->value;
      $unit = $row->unit ?? '';

      $formatted_value = Markup::create(number_format($value, 2) . ' <span class="text-muted">' . htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') . '</span>');

      $table_rows[] = [
        [
          'data' => $this->dateFormatter->format((int) $row->created, 'custom', 'Y-m-d H:i'),
          'class' => ['text-nowrap'],
        ],
        $row->room_name,
        $row->sensor_code,
        $this->metricOptions()[$row->metric] ?? $row->metric,
        [
          'data' => $formatted_value,
          'class' => ['text-end', 'fw-medium'],
        ],
      ];
    }

    return $table_rows;
  }

  private function buildChartData(array $rows) {
    $ordered = array_reverse($rows);
    $labels = [];
    $series = [];

    foreach ($ordered as $row) {
      $label = $this->dateFormatter->format((int) $row->created, 'custom', 'm-d H:i');
      $series_key = $row->room_name . ' / ' . ($this->metricOptions()[$row->metric] ?? $row->metric);

      $labels[$label] = $label;
      $series[$series_key]['label'] = $series_key . ' (' . ($row->unit ?? '') . ')';
      $series[$series_key]['values'][$label] = (float) $row->value;
    }

    $normalized = [];
    foreach ($series as $item) {
      $normalized[] = [
        'label' => $item['label'],
        'values' => array_map(
          fn($label) => $item['values'][$label] ?? NULL,
          array_values($labels)
        ),
      ];
    }

    return [
      'labels' => array_values($labels),
      'series' => $normalized,
    ];
  }

  private function metricOptions() {
    return [
      'temperature' => $this->t('Temperatūra'),
      'humidity' => $this->t('Mitrums'),
      'co2' => $this->t('CO2'),
    ];
  }

}
