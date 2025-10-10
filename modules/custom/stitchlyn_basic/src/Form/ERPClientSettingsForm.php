<?php

namespace Drupal\stitchlyn_basic\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class ERPClientSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['stitchlyn_basic.erp_settings'];
  }

  public function getFormId() {
    return 'stitchlyn_basic_erp_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stitchlyn_basic.erp_settings');

    $form['client_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#default_value' => $config->get('client_name'),
      '#required' => TRUE,
    ];

    $form['client_address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Client Address'),
      '#default_value' => $config->get('client_address'),
    ];

    $form['client_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client contact details'),
      '#default_value' => $config->get('client_contact'),
    ];

    $form['client_gst'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client GST Number'),
      '#default_value' => $config->get('client_gst'),
    ];

    $form['tax_percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Global Tax Percentage'),
      '#default_value' => $config->get('tax_percentage') ?? 18,
      '#step' => 0.01,
      '#description' => $this->t('Set the global tax rate (%) used for quotations.'),
    ];

    $form['client_logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Client Logo'),
      '#upload_location' => 'public://erp_logo/',
      '#default_value' => $config->get('client_logo'),
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if ($fid = $form_state->getValue('client_logo')) {
      $file = File::load($fid[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
      }
    }

    $this->config('stitchlyn_basic.erp_settings')
      ->set('client_name', $form_state->getValue('client_name'))
      ->set('client_address', $form_state->getValue('client_address'))
      ->set('client_contact', $form_state->getValue('client_contact'))
      ->set('client_gst', $form_state->getValue('client_gst'))
      ->set('tax_percentage', $form_state->getValue('tax_percentage'))
      ->set('client_logo', $form_state->getValue('client_logo'))
      ->save();
  }
}