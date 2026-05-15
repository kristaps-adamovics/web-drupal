<?php

namespace Drupal\iot_sensors\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AlertRuleDeleteForm extends ConfirmFormBase {

  protected $database;
  protected $ruleId;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new self($container->get('database'));
  }

  public function getFormId() {
    return 'iot_sensors_alert_rule_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $rule_id = NULL) {
    $this->ruleId = (int) $rule_id;
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    return $this->t('Vai dzēst šo brīdinājuma noteikumu?');
  }

  public function getCancelUrl() {
    return Url::fromRoute('iot_sensors.alerts');
  }

  public function getConfirmText() {
    return $this->t('Dzēst');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->delete('iot_sensors_alert_rules')
      ->condition('id', $this->ruleId)
      ->execute();

    $this->messenger()->addStatus($this->t('Brīdinājuma noteikums dzēsts.'));
    $form_state->setRedirect('iot_sensors.alerts');
  }

}
