<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AlertsFilterForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_alerts_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $filters = []) {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('iot_sensors.alerts')->toString();
    $form['#token'] = FALSE;

    $form['date_from'] = [
      '#type' => 'date',
      '#title' => $this->t('No'),
      '#default_value' => $filters['date_from'] ?? '',
    ];

    $form['date_to'] = [
      '#type' => 'date',
      '#title' => $this->t('Līdz'),
      '#default_value' => $filters['date_to'] ?? '',
    ];

    $form['room_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Telpa'),
      '#options' => [0 => $this->t('- Visas telpas -')] + $this->loadRoomOptions(),
      '#default_value' => (int) ($filters['room_id'] ?? 0),
    ];

    $form['sensor_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Sensors'),
      '#options' => [0 => $this->t('- Visi sensori -')] + $this->loadSensorOptions(),
      '#default_value' => (int) ($filters['sensor_id'] ?? 0),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filtrēt'),
    ];
    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Notīrīt'),
      '#url' => Url::fromRoute('iot_sensors.alerts'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];

    foreach (['date_from', 'date_to', 'room_id', 'sensor_id'] as $key) {
      $value = $form_state->getValue($key);
      if ($value !== NULL && $value !== '' && $value !== '0' && $value !== 0) {
        $query[$key] = $value;
      }
    }

    $form_state->setRedirect('iot_sensors.alerts', [], ['query' => $query]);
  }

  private function loadRoomOptions() {
    return $this->database->select('iot_sensors_rooms', 'r')
      ->fields('r', ['id', 'name'])
      ->orderBy('name')
      ->execute()
      ->fetchAllKeyed();
  }

  private function loadSensorOptions() {
    $query = $this->database->select('iot_sensors_sensors', 's');
    $query->join('iot_sensors_rooms', 'r', 'r.id = s.room_id');
    $query->fields('s', ['id', 'sensor_code']);
    $query->addField('r', 'name', 'room_name');
    $query->orderBy('r.name');
    $query->orderBy('s.sensor_code');

    $options = [];
    foreach ($query->execute()->fetchAll() as $sensor) {
      $options[$sensor->id] = $sensor->room_name . ' - ' . $sensor->sensor_code;
    }

    return $options;
  }

}
