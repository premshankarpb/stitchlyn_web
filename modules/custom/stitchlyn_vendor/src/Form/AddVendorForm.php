<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

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

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
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

    $form['field_vendor_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
    ];

    $form['field_billing_address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Billing Address'),
    ];

    $form['field_gst_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GST Number'),
    ];

    $form['field_about'] = [
      '#type' => 'textarea',
      '#title' => $this->t('About'),
    ];

    $vocabulary = 'status';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary);
    $options = [];

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $form['field_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a status -'),
      '#required' => TRUE,
    ];

    $vocabulary_2 = 'vendor_type';
    $vendor_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_2);
    $options_vendor_type = [];

    foreach ($vendor_terms as $term) {
      $options_vendor_type[$term->tid] = $term->name;
    }

    $form['field_vendor_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Vendor Type'),
      '#options' => $options_vendor_type,
      '#empty_option' => $this->t('- Select a Type -'),
      '#required' => TRUE,
    ];    

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Vendor'),
    ];

    //$form['#theme'] = 'vendor_add_form';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::create([
      'name' => $form_state->getValue('username'),
      'mail' => $form_state->getValue('email'),
      'pass' => $form_state->getValue('password'),
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
    //$profile->set('field_vendor_location', $form_state->getValue('field_vendor_location'));
    $profile->set('field_billing_address', $form_state->getValue('field_billing_address'));
    $profile->set('field_gst_number', $form_state->getValue('field_gst_number'));
    $profile->set('field_remarks', $form_state->getValue('field_about'));
    $profile->set('field_status', $form_state->getValue('field_status'));
    $profile->set('field_vendor_type', $form_state->getValue('field_vendor_type'));

    $profile->save();

    $this->messenger()->addStatus($this->t('Vendor %name created successfully.', ['%name' => $user->getAccountName()]));
  }
}
