<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class VendorEditForm extends FormBase {

  protected $request;

  public function __construct(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('request_stack'));
  }

  public function getFormId() {
    return 'stitchlyn_vendor_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    if (!$user instanceof User || !$user->hasRole('vendor')) {
      $form['message'] = ['#markup' => $this->t('Invalid vendor user.')];
      return $form;
    }

    // Load the profile.
    $profile = Profile::loadByUser($user, 'vendor_profile');

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vendor Name'),
      '#default_value' => $user->getDisplayName(),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $user->getEmail(),
      '#required' => TRUE,
    ];

    // Add profile fields
    if ($profile) {
      $form['field_vendor_phone'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phone'),
        '#default_value' => $profile->get('field_vendor_phone')->value ?? '',
      ];
      $form['field_vendor_company'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Company Name'),
        '#default_value' => $profile->get('field_vendor_company')->value ?? '',
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Vendor'),
      '#button_type' => 'primary',
    ];

    $form['#theme'] = 'stitchlyn_vendor_edit_form';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $this->request->get('user');
    $user = User::load($uid);

    if ($user) {
      $user->setEmail($form_state->getValue('email'));
      $user->setUsername($form_state->getValue('name'));
      $user->save();

      $profile = Profile::loadByUser($user, 'vendor_profile');
      if ($profile) {
        $profile->set('field_vendor_phone', $form_state->getValue('field_vendor_phone'));
        $profile->set('field_vendor_company', $form_state->getValue('field_vendor_company'));
        $profile->save();
      }

      \Drupal::messenger()->addStatus($this->t('Vendor updated successfully.'));
    } else {
      \Drupal::messenger()->addError($this->t('Failed to update vendor.'));
    }
  }
}
