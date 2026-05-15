<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RoomDeleteForm extends ConfirmFormBase {

  protected $database;
  protected $roomId;
  protected $room;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_room_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $room_id = NULL) {
    $this->roomId = (int) $room_id;
    $this->room = $this->loadRoom($this->roomId);
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    if ($this->room) {
      return $this->t('Vai dzēst telpu "@name"?', ['@name' => $this->room->name]);
    }

    return $this->t('Telpa nav atrasta.');
  }

  public function getCancelUrl() {
    return Url::fromRoute('iot_sensors.building');
  }

  public function getConfirmText() {
    return $this->t('Dzēst');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->room) {
      $form_state->setRedirect('iot_sensors.building');
      return;
    }

    $sensor_ids = $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['id'])
      ->condition('room_id', $this->roomId)
      ->execute()
      ->fetchCol();

    if (!empty($sensor_ids)) {
      $this->database->delete('iot_sensors_alert_rules')
        ->condition('sensor_id', $sensor_ids, 'IN')
        ->execute();

      $this->database->delete('iot_sensors_readings')
        ->condition('sensor_id', $sensor_ids, 'IN')
        ->execute();
    }

    $this->database->delete('iot_sensors_sensors')
      ->condition('room_id', $this->roomId)
      ->execute();

    $this->database->delete('iot_sensors_rooms')
      ->condition('id', $this->roomId)
      ->execute();

    $this->messenger()->addStatus($this->t('Telpa, tās sensori, mērījumi un brīdinājumi dzēsti.'));
    $form_state->setRedirect('iot_sensors.building');
  }

  private function loadRoom($room_id) {
    return $this->database->select('iot_sensors_rooms', 'r')
      ->fields('r', ['id', 'name'])
      ->condition('id', $room_id)
      ->execute()
      ->fetchObject();
  }

}
