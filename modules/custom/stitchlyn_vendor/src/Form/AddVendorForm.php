<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

class AddVendorForm extends FormBase {

  public function getFormId() {
    return 'add_vendor_user_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['field_vendor_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor Name'),
      '#required' => TRUE,
    ];

    $form['field_contact_person'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Person'),
    ];

    $form['field_phone_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone Number'),
    ];

    $form['field_billing_address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Billing Address'),
    ];

    $form['field_gst_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GST Number'),
    ];

    $form['field_status'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Status'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['status']],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Vendor'),
    ];

    $form['#theme'] = 'vendor_add_form';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::create([
      'name' => $form_state->getValue('username'),
      'mail' => $form_state->getValue('email'),
      'status' => 1,
      'roles' => ['vendor'],
    ]);
    $user->save();

    $profile = Profile::create([
      'type' => 'vendor',
      'uid' => $user->id(),
    ]);

    $profile->set('field_vendor_name', $form_state->getValue('field_vendor_name'));
    $profile->set('field_contact_person', $form_state->getValue('field_contact_person'));
    $profile->set('field_phone_number', $form_state->getValue('field_phone_number'));
    $profile->set('field_billing_address', $form_state->getValue('field_billing_address'));
    $profile->set('field_gst_number', $form_state->getValue('field_gst_number'));
    $profile->set('field_status', $form_state->getValue('field_status'));

    $profile->save();

    $this->messenger()->addStatus($this->t('Vendor %name created successfully.', ['%name' => $user->getAccountName()]));
  }
}
