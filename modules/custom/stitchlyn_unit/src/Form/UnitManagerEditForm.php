<?php

namespace Drupal\stitchlyn_unit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

class UnitManagerEditForm extends FormBase {

  public function getFormId() {
    return 'stitchlyn_unit_manager_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    $account = User::load($user);

    if (!$account || !$account->hasRole('unit_manager')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    // Load profile of type 'unit_manager'
    $profile = Profile::loadByUser($account, 'unit_manager');

    if (!$profile) {
      $this->messenger()->addError('Profile not found.');
      return $form;
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $account->getDisplayName(),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => $profile->get('field_unit_phone')->value,
    ];

    $form['unit'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Unit'),
      '#target_type' => 'taxonomy_term',
      '#default_value' => $profile->get('field_unit')->entity ?? NULL,
      '#required' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['units'],
      ],
    ];

    $form['uid'] = [
      '#type' => 'value',
      '#value' => $account->id(),
    ];

    $form['profile_id'] = [
      '#type' => 'value',
      '#value' => $profile->id(),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
    ];

    $form['#theme'] = 'stitchlyn_unit_manager_edit_form';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->getValue('uid');
    $profile_id = $form_state->getValue('profile_id');

    $account = User::load($uid);
    $profile = Profile::load($profile_id);

    if ($account && $profile) {
      $account->setUsername($form_state->getValue('name'));
      $account->save();

      $profile->set('field_unit_phone', $form_state->getValue('phone'));
      $profile->set('field_unit', $form_state->getValue('unit'));
      $profile->save();

      $this->messenger()->addStatus($this->t('Unit Manager updated.'));
    }
  }
}
