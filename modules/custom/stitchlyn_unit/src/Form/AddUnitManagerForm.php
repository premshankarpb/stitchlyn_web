<?php

namespace Drupal\stitchlyn_unit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

class AddUnitManagerForm extends FormBase {

  public function getFormId() {
    return 'add_unit_manager_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['field_phone_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#required' => TRUE,
    ];

    $form['field_unit'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Unit'),
      '#target_type' => 'taxonomy_term',
      '#required' => TRUE,
      '#selection_settings' => ['target_bundles' => ['production_units']],
    ];

    $form['field_vendor_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
    ];

    $form['field_about'] = [
      '#type' => 'textarea',
      '#title' => $this->t('About'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Unit Manager'),
    ];

    //$form['#theme'] = 'unit_manager_add_form';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // 1. Create the user.
    $user = User::create([
      'name' => $form_state->getValue('email'),
      'mail' => $form_state->getValue('email'),
      'pass' => $form_state->getValue('password'),
      'status' => 1,
    ]);
    $user->addRole('unit_manager');
    $user->save();

    // 2. Create the profile for that user.
    $profile = Profile::create([
      'type' => 'unit_manager',
      'uid' => $user->id(),
      'field_name' => $form_state->getValue('field_name'),
      'field_phone_number' => $form_state->getValue('field_phone_number'),
      'field_unit' => $form_state->getValue('field_unit'),
      'field_vendor_location' => $form_state->getValue('field_vendor_location'),
      'field_about' => $form_state->getValue('field_about'),
    ]);
    $profile->save();

    $this->messenger()->addStatus($this->t('Unit Manager account created successfully.'));
  }
}
