<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AlertRuleForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_alert_rule_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sensor_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Telpa un sensors'),
      '#options' => $this->loadSensorOptions(),
      '#empty_option' => $this->t('- Izvēlies sensoru -'),
      '#required' => TRUE,
    ];

    $form['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Nosacījums'),
      '#options' => [
        '>' => '>',
        '<' => '<',
        '>=' => '>=',
        '<=' => '<=',
      ],
      '#required' => TRUE,
    ];

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Robežvērtība'),
      '#step' => '0.01',
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Brīdinājuma teksts'),
      '#maxlength' => 255,
      '#placeholder' => $this->t('Piemēram, temperatūra ir pārāk augsta'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pievienot brīdinājumu'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sensor_id = (int) $form_state->getValue('sensor_id');
    $room_id = $this->getRoomId($sensor_id);

    $this->database->insert('iot_sensors_alert_rules')
      ->fields([
        'room_id' => $room_id,
        'sensor_id' => $sensor_id,
        'operator' => $form_state->getValue('operator'),
        'threshold' => $form_state->getValue('threshold'),
        'message' => $form_state->getValue('message'),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Brīdinājums pievienots.'));
    $form_state->setRedirect('iot_sensors.alerts');
  }

  private function loadSensorOptions() {
    $query = $this->database->select('iot_sensors_sensors', 's');
    $query->join('iot_sensors_rooms', 'r', 'r.id = s.room_id');
    $query->fields('s', ['id', 'sensor_code', 'metric']);
    $query->addField('r', 'name', 'room_name');
    $query->orderBy('r.name');
    $query->orderBy('s.sensor_code');

    $options = [];
    foreach ($query->execute()->fetchAll() as $sensor) {
      $options[$sensor->id] = $sensor->room_name . ' - ' . $sensor->sensor_code . ' (' . $sensor->metric . ')';
    }

    return $options;
  }

  private function getRoomId($sensor_id) {
    return (int) $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['room_id'])
      ->condition('id', $sensor_id)
      ->execute()
      ->fetchField();
  }

}
