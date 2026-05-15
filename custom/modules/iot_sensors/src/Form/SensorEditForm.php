<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SensorEditForm extends FormBase {

  protected $database;
  protected $sensor;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_sensor_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $sensor_id = NULL) {
    $this->sensor = $this->loadSensor($sensor_id);

    if (!$this->sensor) {
      $form['missing'] = ['#markup' => $this->t('Sensors nav atrasts.')];
      return $form;
    }

    $form['sensor_id'] = [
      '#type' => 'hidden',
      '#value' => $this->sensor->id,
    ];

    $form['room_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Telpa'),
      '#options' => $this->loadRoomOptions(),
      '#default_value' => $this->sensor->room_id,
      '#required' => TRUE,
    ];

    $form['sensor_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sensora kods'),
      '#default_value' => $this->sensor->sensor_code,
      '#required' => TRUE,
      '#maxlength' => 64,
    ];

    $form['metric'] = [
      '#type' => 'select',
      '#title' => $this->t('Sensora tips'),
      '#options' => [
        'temperature' => $this->t('Temperatūra'),
        'humidity' => $this->t('Mitrums'),
        'co2' => $this->t('CO2'),
      ],
      '#default_value' => $this->sensor->metric,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Saglabāt'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $exists = $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['id'])
      ->condition('sensor_code', $form_state->getValue('sensor_code'))
      ->condition('id', $form_state->getValue('sensor_id'), '<>')
      ->execute()
      ->fetchField();

    if ($exists) {
      $form_state->setErrorByName('sensor_code', $this->t('Šāds sensora kods jau eksistē.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $metric = $form_state->getValue('metric');

    $this->database->update('iot_sensors_sensors')
      ->fields([
        'room_id' => $form_state->getValue('room_id'),
        'sensor_code' => $form_state->getValue('sensor_code'),
        'metric' => $metric,
        'unit' => $this->unitForMetric($metric),
      ])
      ->condition('id', $form_state->getValue('sensor_id'))
      ->execute();

    $this->messenger()->addStatus($this->t('Sensors saglabāts.'));
    $form_state->setRedirect('iot_sensors.building');
  }

  private function loadSensor($sensor_id) {
    return $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['id', 'room_id', 'sensor_code', 'metric'])
      ->condition('id', $sensor_id)
      ->execute()
      ->fetchObject();
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

}
