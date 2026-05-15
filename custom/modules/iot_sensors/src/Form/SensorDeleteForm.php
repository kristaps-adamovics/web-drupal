<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SensorDeleteForm extends ConfirmFormBase {

  protected $database;
  protected $sensorId;
  protected $sensor;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_sensor_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $sensor_id = NULL) {
    $this->sensorId = (int) $sensor_id;
    $this->sensor = $this->loadSensor($this->sensorId);
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    if ($this->sensor) {
      return $this->t('Vai dzēst sensoru "@code"?', ['@code' => $this->sensor->sensor_code]);
    }

    return $this->t('Sensors nav atrasts.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('iot_sensors.building');
  }

  public function getConfirmText() {
    return $this->t('Dzēst');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->sensor) {
      $form_state->setRedirect('iot_sensors.building');
      return;
    }

    $this->database->delete('iot_sensors_alert_rules')
      ->condition('sensor_id', $this->sensorId)
      ->execute();

    $this->database->delete('iot_sensors_readings')
      ->condition('sensor_id', $this->sensorId)
      ->execute();

    $this->database->delete('iot_sensors_sensors')
      ->condition('id', $this->sensorId)
      ->execute();

    $this->messenger()->addStatus($this->t('Sensors, tā mērījumi un brīdinājumi dzēsti.'));
    $form_state->setRedirect('iot_sensors.building');
  }

  private function loadSensor($sensor_id) {
    return $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['id', 'sensor_code'])
      ->condition('id', $sensor_id)
      ->execute()
      ->fetchObject();
  }

}
