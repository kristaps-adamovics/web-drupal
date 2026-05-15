<?php

namespace Drupal\iot_sensors\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\iot_sensors\Form\RoomForm;
use Drupal\iot_sensors\Form\SensorForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BuildingController extends ControllerBase {

  protected $database;
  protected $formBuilder;

  public function __construct(Connection $database, FormBuilderInterface $formBuilder) {
    $this->database = $database;
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('form_builder'),
    );
  }

  public function overview() {
    $rooms = $this->loadRooms();
    $sensors = $this->loadSensors();

    return [
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'library' => ['iot_sensors/building'],
      ],
      'plan_title' => [
        '#markup' => '<h2>Ēkas plāns</h2>',
      ],
      'plan' => $this->buildPlan($rooms, $sensors),
      'forms' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['iot-building-forms']],
        'room' => [
          '#type' => 'details',
          '#title' => $this->t('Pievienot telpu'),
          '#open' => TRUE,
          'form' => $this->formBuilder->getForm(RoomForm::class),
        ],
        'sensor' => [
          '#type' => 'details',
          '#title' => $this->t('Pievienot sensoru'),
          '#open' => TRUE,
          'form' => $this->formBuilder->getForm(SensorForm::class),
        ],
      ],
      'rooms_title' => [
        '#markup' => '<h2>Telpu saraksts</h2>',
      ],
      'rooms' => $this->buildRoomsTable($rooms, $sensors),
      'sensors_title' => [
        '#markup' => '<h2>Sensoru saraksts</h2>',
      ],
      'sensors' => $this->buildSensorsTable($sensors),
    ];
  }

  private function loadRooms() {
    return $this->database->select('iot_sensors_rooms', 'r')
      ->fields('r', ['id', 'name', 'floor'])
      ->orderBy('floor')
      ->orderBy('name')
      ->execute()
      ->fetchAll();
  }

  private function loadSensors() {
    $query = $this->database->select('iot_sensors_sensors', 's');
    $query->join('iot_sensors_rooms', 'r', 'r.id = s.room_id');
    $query->fields('s', ['id', 'room_id', 'sensor_code', 'metric', 'unit']);
    $query->addField('r', 'name', 'room_name');
    $query->addField('r', 'floor', 'room_floor');
    $query->orderBy('r.floor');
    $query->orderBy('r.name');
    $query->orderBy('s.metric');
    $query->orderBy('s.sensor_code');
    return $query->execute()->fetchAll();
  }

  private function buildPlan(array $rooms, array $sensors) {
    $items = [];

    foreach ($rooms as $room) {
      $room_sensors = $this->getRoomSensors($room->id, $sensors);
      $links = [];

      foreach ($room_sensors as $sensor) {
        $links['sensor_' . $sensor->id] = [
          '#type' => 'link',
          '#title' => $this->t('ID @id: @code (@metric)', [
            '@id' => $sensor->id,
            '@code' => $sensor->sensor_code,
            '@metric' => $this->metricName($sensor->metric),
          ]),
          '#url' => Url::fromRoute('iot_sensors.reports', [], [
            'query' => [
              'room_id' => $room->id,
              'metric' => $sensor->metric,
              'sensor_id' => $sensor->id,
            ],
          ]),
          '#attributes' => ['class' => ['iot-plan-sensor']],
        ];
      }

      $items['room_' . $room->id] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['iot-plan-room']],
        'name' => [
          '#markup' => '<strong>ID ' . $room->id . ': ' . Html::escape($room->name) . '</strong>',
        ],
        'floor' => [
          '#markup' => '<div>Stāvs: ' . Html::escape($room->floor ?: '-') . '</div>',
        ],
        'sensors' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['iot-plan-sensors']],
          'items' => $links ?: ['#markup' => '<span>Nav sensoru</span>'],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['iot-building-plan']],
      'items' => $items ?: ['#markup' => '<p>Telpas vēl nav pievienotas.</p>'],
    ];
  }

  private function buildRoomsTable(array $rooms, array $sensors) {
    $rows = [];

    foreach ($rooms as $room) {
      $rows[] = [
        $room->id,
        $room->name,
        $room->floor ?: '-',
        count($this->getRoomSensors($room->id, $sensors)),
        [
          'data' => $this->buildRoomSensorLinks($this->getRoomSensors($room->id, $sensors)),
        ],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Labot'),
                'url' => Url::fromRoute('iot_sensors.room_edit', ['room_id' => $room->id]),
              ],
              'delete' => [
                'title' => $this->t('Dzēst'),
                'url' => Url::fromRoute('iot_sensors.room_delete', ['room_id' => $room->id]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('ID'), $this->t('Telpa'), $this->t('Stāvs'), $this->t('Sensoru skaits'), $this->t('Sensori'), $this->t('Darbības')],
      '#rows' => $rows,
      '#empty' => $this->t('Nav pievienotu telpu.'),
    ];
  }

  private function buildSensorsTable(array $sensors) {
    $rows = [];

    foreach ($sensors as $sensor) {
      $rows[] = [
        $sensor->id,
        $sensor->room_name,
        $sensor->sensor_code,
        $this->metricName($sensor->metric),
        $sensor->unit,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Pārskats'),
            '#url' => Url::fromRoute('iot_sensors.reports', [], [
              'query' => [
                'room_id' => $sensor->room_id,
                'metric' => $sensor->metric,
                'sensor_id' => $sensor->id,
              ],
            ]),
          ],
        ],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Labot'),
                'url' => Url::fromRoute('iot_sensors.sensor_edit', ['sensor_id' => $sensor->id]),
              ],
              'delete' => [
                'title' => $this->t('Dzēst'),
                'url' => Url::fromRoute('iot_sensors.sensor_delete', ['sensor_id' => $sensor->id]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('ID'), $this->t('Telpa'), $this->t('Sensora kods'), $this->t('Tips'), $this->t('Mērvienība'), $this->t('Dati'), $this->t('Darbības')],
      '#rows' => $rows,
      '#empty' => $this->t('Nav pievienotu sensoru.'),
    ];
  }

  private function getRoomSensors($room_id, array $sensors) {
    return array_values(array_filter($sensors, function ($sensor) use ($room_id) {
      return (int) $sensor->room_id === (int) $room_id;
    }));
  }

  private function metricName($metric) {
    $names = [
      'temperature' => $this->t('Temperatūra'),
      'humidity' => $this->t('Mitrums'),
      'co2' => $this->t('CO2'),
    ];

    return $names[$metric] ?? $metric;
  }

  private function buildRoomSensorLinks(array $sensors) {
    if (!$sensors) {
      return ['#markup' => $this->t('Nav sensoru.')];
    }

    $items = [];
    foreach ($sensors as $sensor) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('ID @id: @code (@metric)', [
          '@id' => $sensor->id,
          '@code' => $sensor->sensor_code,
          '@metric' => $this->metricName($sensor->metric),
        ]),
        '#url' => Url::fromRoute('iot_sensors.reports', [], [
          'query' => [
            'room_id' => $sensor->room_id,
            'metric' => $sensor->metric,
            'sensor_id' => $sensor->id,
          ],
        ]),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['iot-room-sensors']],
    ];
  }

}
