<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class TaxConfigController extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['stitchlyn_quotation.settings'];
  }

  public function getFormId() {
    return 'stitchlyn_tax_config_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stitchlyn_quotation.settings');
    $form['tax_percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Global Tax Percentage'),
      '#default_value' => $config->get('tax_percentage') ?? 5,
      '#step' => 0.01,
      '#description' => $this->t('Set the global tax rate (%) used for quotations.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('stitchlyn_quotation.settings')
      ->set('tax_percentage', $form_state->getValue('tax_percentage'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
