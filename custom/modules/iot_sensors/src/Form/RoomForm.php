<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RoomForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_room_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Telpas nosaukums'),
      '#required' => TRUE,
      '#maxlength' => 128,
      '#placeholder' => $this->t('Piemēram, 305. telpa'),
    ];

    $form['floor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stāvs'),
      '#maxlength' => 32,
      '#placeholder' => $this->t('Piemēram, 3'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pievienot telpu'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $room_id = $this->database->insert('iot_sensors_rooms')
      ->fields([
        'name' => $form_state->getValue('name'),
        'floor' => $form_state->getValue('floor'),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Telpa pievienota. Telpas ID: @id', ['@id' => $room_id]));
    $form_state->setRedirect('iot_sensors.building');
  }

}
