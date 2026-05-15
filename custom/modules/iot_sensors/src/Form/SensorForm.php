<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SensorForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_sensor_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['room_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Telpa'),
      '#options' => $this->loadRoomOptions(),
      '#empty_option' => $this->t('- Izvēlies telpu -'),
      '#required' => TRUE,
    ];

    $form['metric'] = [
      '#type' => 'select',
      '#title' => $this->t('Sensora tips'),
      '#options' => [
        'temperature' => $this->t('Temperatūras sensors'),
        'humidity' => $this->t('Mitruma sensors'),
        'co2' => $this->t('CO2 sensor'),
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pievienot sensoru'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('room_id')) {
      $form_state->setErrorByName('room_id', $this->t('Vispirms jāizvēlas telpa.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $room_id = (int) $form_state->getValue('room_id');
    $metric = $form_state->getValue('metric');
    $unit = $this->unitForMetric($metric);
    $temporary_code = $this->makeTemporarySensorCode($metric, $room_id);

    $sensor_id = $this->database->insert('iot_sensors_sensors')
      ->fields([
        'room_id' => $room_id,
        'sensor_code' => $temporary_code,
        'metric' => $metric,
        'unit' => $unit,
      ])
      ->execute();

    $sensor_code = $this->makeSensorCode($metric, $room_id, $sensor_id);
    $this->database->update('iot_sensors_sensors')
      ->fields(['sensor_code' => $sensor_code])
      ->condition('id', $sensor_id)
      ->execute();

    $this->messenger()->addStatus($this->t('Sensors pievienots. Sensora ID: @id', ['@id' => $sensor_id]));
    $form_state->setRedirect('iot_sensors.building');
  }

  private function loadRoomOptions() {
    return $this->database->select('iot_sensors_rooms', 'r')
      ->fields('r', ['id', 'name'])
      ->orderBy('name')
      ->execute()
      ->fetchAllKeyed();
  }

  private function unitForMetric($metric) {
    if ($metric === 'humidity') {
      return '%';
    }

    if ($metric === 'co2') {
      return 'ppm';
    }

    return 'C';
  }

  private function makeSensorCode($metric, $room_id, $sensor_id) {
    $prefixes = [
      'temperature' => 'TMP',
      'humidity' => 'HUM',
      'co2' => 'CO2',
    ];

    $prefix = $prefixes[$metric] ?? 'SNS';
    return $prefix . '-' . $room_id . '-' . $sensor_id;
  }

  private function makeTemporarySensorCode($metric, $room_id) {
    $prefixes = [
      'temperature' => 'TMP',
      'humidity' => 'HUM',
      'co2' => 'CO2',
    ];

    $prefix = $prefixes[$metric] ?? 'SNS';
    return $prefix . '-' . $room_id . '-NEW-' . uniqid();
  }

}
