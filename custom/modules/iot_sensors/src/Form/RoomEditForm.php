<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RoomEditForm extends FormBase {

  protected $database;
  protected $room;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_room_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $room_id = NULL) {
    $this->room = $this->loadRoom($room_id);

    if (!$this->room) {
      $form['missing'] = ['#markup' => $this->t('Telpa nav atrasta.')];
      return $form;
    }

    $form['room_id'] = [
      '#type' => 'hidden',
      '#value' => $this->room->id,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Telpas nosaukums'),
      '#default_value' => $this->room->name,
      '#required' => TRUE,
      '#maxlength' => 128,
    ];

    $form['floor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stāvs'),
      '#default_value' => $this->room->floor,
      '#maxlength' => 32,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Saglabāt'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->update('iot_sensors_rooms')
      ->fields([
        'name' => $form_state->getValue('name'),
        'floor' => $form_state->getValue('floor'),
      ])
      ->condition('id', $form_state->getValue('room_id'))
      ->execute();

    $this->messenger()->addStatus($this->t('Telpa saglabāta.'));
    $form_state->setRedirect('iot_sensors.building');
  }

  private function loadRoom($room_id) {
    return $this->database->select('iot_sensors_rooms', 'r')
      ->fields('r', ['id', 'name', 'floor'])
      ->condition('id', $room_id)
      ->execute()
      ->fetchObject();
  }

}
