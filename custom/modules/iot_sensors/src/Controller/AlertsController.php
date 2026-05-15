<?php

namespace Drupal\iot_sensors\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\iot_sensors\Form\AlertRuleForm;
use Drupal\iot_sensors\Form\AlertsFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AlertsController extends ControllerBase {

  protected $database;
  protected $dateFormatter;
  protected $formBuilder;
  protected $requestStack;

  public function __construct(Connection $database, DateFormatterInterface $dateFormatter, FormBuilderInterface $formBuilder, RequestStack $requestStack) {
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->formBuilder = $formBuilder;
    $this->requestStack = $requestStack;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('form_builder'),
      $container->get('request_stack'),
    );
  }

  public function overview() {
    if (!$this->database->schema()->tableExists('iot_sensors_alert_rules')) {
      return [
        'message' => [
          '#markup' => '<p>Brīdinājumu tabula vēl nav izveidota. Palaid Drupal datubāzes atjauninājumus un notīri kešatmiņu.</p>',
        ],
      ];
    }

    $filters = $this->getFilters();
    $alerts = $this->loadAlerts($filters);
    $rules = $this->loadRules();

    return [
      '#cache' => [
        'contexts' => ['url.query_args'],
        'max-age' => 0,
      ],
      'rule_title' => [
        '#markup' => '<h2>Pievienot brīdinājumu</h2>',
      ],
      'rule_form' => $this->formBuilder->getForm(AlertRuleForm::class),
      'rules_title' => [
        '#markup' => '<h2>Brīdinājumu noteikumi</h2>',
      ],
      'rules' => $this->buildRulesTable($rules),
      'filter_title' => [
        '#markup' => '<h2>Brīdinājumu pārskats</h2>',
      ],
      'filter' => $this->formBuilder->getForm(AlertsFilterForm::class, $filters),
      'summary' => [
        '#markup' => '<p><strong>Atrasti ' . count($alerts) . ' brīdinājumi.</strong></p>',
      ],
      'alerts' => $this->buildAlertsTable($alerts),
    ];
  }

  private function getFilters() {
    $query = $this->requestStack->getCurrentRequest()?->query;
    $default_dates = $this->getDefaultDateRange();

    return [
      'date_from' => (string) ($query?->get('date_from') ?: $default_dates['date_from']),
      'date_to' => (string) ($query?->get('date_to') ?: $default_dates['date_to']),
      'room_id' => (int) ($query?->get('room_id') ?: 0),
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
      $latest = \Drupal::time()->getRequestTime();
    }

    return [
      'date_from' => date('Y-m-d', strtotime('-7 days', $latest)),
      'date_to' => date('Y-m-d', $latest),
    ];
  }

  private function loadAlerts(array $filters) {
    $query = $this->database->select('iot_sensors_readings', 'reading');
    $query->join('iot_sensors_sensors', 'sensor', 'sensor.id = reading.sensor_id');
    $query->join('iot_sensors_rooms', 'room', 'room.id = sensor.room_id');
    $query->join('iot_sensors_alert_rules', 'rule', 'rule.sensor_id = sensor.id');
    $query->fields('reading', ['value', 'created']);
    $query->fields('sensor', ['id', 'sensor_code', 'metric', 'unit']);
    $query->fields('room', ['name']);
    $query->fields('rule', ['id', 'operator', 'threshold', 'message']);

    $from = strtotime($filters['date_from'] . ' 00:00:00') ?: 0;
    $to = strtotime($filters['date_to'] . ' 23:59:59') ?: \Drupal::time()->getRequestTime();

    $query->condition('reading.created', $from, '>=');
    $query->condition('reading.created', $to, '<=');

    if ($filters['room_id'] > 0) {
      $query->condition('room.id', $filters['room_id']);
    }

    if ($filters['sensor_id'] > 0) {
      $query->condition('sensor.id', $filters['sensor_id']);
    }

    $or = $query->orConditionGroup();
    $or->condition($query->andConditionGroup()
      ->condition('rule.operator', '>')
      ->where('reading.value > rule.threshold'));
    $or->condition($query->andConditionGroup()
      ->condition('rule.operator', '<')
      ->where('reading.value < rule.threshold'));
    $or->condition($query->andConditionGroup()
      ->condition('rule.operator', '>=')
      ->where('reading.value >= rule.threshold'));
    $or->condition($query->andConditionGroup()
      ->condition('rule.operator', '<=')
      ->where('reading.value <= rule.threshold'));
    $query->condition($or);

    $query->orderBy('reading.created', 'DESC');
    $query->range(0, 200);

    return $query->execute()->fetchAll();
  }

  private function loadRules() {
    $query = $this->database->select('iot_sensors_alert_rules', 'rule');
    $query->join('iot_sensors_sensors', 'sensor', 'sensor.id = rule.sensor_id');
    $query->join('iot_sensors_rooms', 'room', 'room.id = rule.room_id');
    $query->fields('rule', ['id', 'operator', 'threshold', 'message']);
    $query->fields('sensor', ['sensor_code', 'metric', 'unit']);
    $query->addField('room', 'name', 'room_name');
    $query->orderBy('rule.id', 'DESC');
    return $query->execute()->fetchAll();
  }

  private function buildRulesTable(array $rules) {
    $rows = [];

    foreach ($rules as $rule) {
      $rows[] = [
        $rule->id,
        $rule->room_name,
        $rule->sensor_code,
        $this->metricName($rule->metric),
        $rule->operator . ' ' . number_format((float) $rule->threshold, 2) . ' ' . $rule->unit,
        $rule->message ?: '-',
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Dzēst'),
            '#url' => Url::fromRoute('iot_sensors.alert_rule_delete', ['rule_id' => $rule->id]),
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('ID'), $this->t('Telpa'), $this->t('Sensors'), $this->t('Tips'), $this->t('Robežvērtība'), $this->t('Teksts'), $this->t('Darbība')],
      '#rows' => $rows,
      '#empty' => $this->t('Brīdinājumu noteikumi vēl nav pievienoti.'),
    ];
  }

  private function buildAlertsTable(array $alerts) {
    $rows = [];

    foreach ($alerts as $alert) {
      $rows[] = [
        $this->dateFormatter->format((int) $alert->created, 'custom', 'Y-m-d H:i'),
        $alert->name,
        $alert->sensor_code,
        $this->metricName($alert->metric),
        number_format((float) $alert->value, 2) . ' ' . $alert->unit,
        $alert->operator . ' ' . number_format((float) $alert->threshold, 2) . ' ' . $alert->unit,
        $alert->message ?: $this->t('Pārsniegta robežvērtība'),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('Laiks'), $this->t('Telpa'), $this->t('Sensors'), $this->t('Tips'), $this->t('Vērtība'), $this->t('Noteikums'), $this->t('Brīdinājums')],
      '#rows' => $rows,
      '#empty' => $this->t('Izvēlētajā periodā brīdinājumi nav atrasti.'),
    ];
  }

  private function metricName($metric) {
    $names = [
      'temperature' => $this->t('Temperatūra'),
      'humidity' => $this->t('Mitrums'),
      'co2' => $this->t('CO2'),
    ];

    return $names[$metric] ?? $metric;
  }

}
