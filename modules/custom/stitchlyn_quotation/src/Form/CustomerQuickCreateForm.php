<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\Core\Ajax\HtmlCommand;

class CustomerQuickCreateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'customer_quick_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Disable cache for modal form.
    $form['#cache'] = [
      'max-age' => 0,
    ];

    // Wrapper for AJAX validation refresh.
    $form['#prefix'] = '<div id="customer-popup-form-wrapper">';
    $form['#suffix'] = '</div>';

    // ======================
    // USER FIELDS
    // ======================

		$form['field_first_name'] = [
			'#type' => 'textfield',
			'#title' => $this->t('First Name'),
			'#required' => TRUE,
		];

		$form['field_last_name'] = [
			'#type' => 'textfield',
			'#title' => $this->t('Last Name'),
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

    // ======================
    // PROFILE FIELDS (UI ONLY FOR NOW)
    // ======================

    $form['field_phone_number'] = [
      '#type' => 'tel',
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

    $form['field_associated_diocese'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Associated Diocese'),
    ];

    $form['field_division'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Division or Order'),
    ];

    $form['field_parish'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parish'),
    ];

    $form['field_name_of_referrer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of Referrer'),
    ];

    $form['field_referrer_contact'] = [
      '#type' => 'tel',
      '#title' => $this->t('Referrer Contact'),
			'#attributes' => [ 'class' => ['js-intl-phone'], ],
    ];

    $form['field_date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
    ];

    $form['field_ordination_day'] = [
      '#type' => 'date',
      '#title' => $this->t('Ordination Day'),
    ];

    $form['field_remarks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
    ];

    // ======================
    // ACTIONS
    // ======================

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Customer'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'customer-popup-form-wrapper',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving customer...'),
        ],
      ],
    ];

		$form['#cache'] = [
			'max-age' => 0,
		];

		$form['#theme'] = 'stitchlyn_quotation_customer_add_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if (user_load_by_mail($form_state->getValue('email'))) {

      $form_state->setErrorByName(
        'email',
        $this->t('Email already exists.')
      );
    }
  }

  /**
   * Mandatory submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Keep empty.
  }

  /**
   * AJAX submit callback.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {

    // Validation errors.
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    // Create user.
		$user = User::create([
			'name' => $form_state->getValue('email'),
			'mail' => $form_state->getValue('email'),
			'status' => 1,
			'pass' => $form_state->getValue('password'),

			// User fields
			'field_first_name' => $form_state->getValue('field_first_name'),
			'field_last_name' => $form_state->getValue('field_last_name'),
		]);

    $user->save();

		// ======================
		// CREATE PROFILE
		// ======================

		$profile = Profile::create([
			'type' => 'customer',
			'uid' => $user->id(),
		]);

		$profile->set('field_phone_number', $form_state->getValue('field_phone_number'));
		$profile->set('field_billing_address', $form_state->getValue('field_billing_address'));
		$profile->set('field_gst_number', $form_state->getValue('field_gst_number'));
		$profile->set('field_associated_diocese', $form_state->getValue('field_associated_diocese'));
		$profile->set('field_division', $form_state->getValue('field_division'));
		$profile->set('field_parish', $form_state->getValue('field_parish'));
		$profile->set('field_name_of_referrer', $form_state->getValue('field_name_of_referrer'));
		$profile->set('field_referrer_contact', $form_state->getValue('field_referrer_contact'));
		$profile->set('field_date_of_birth', $form_state->getValue('field_date_of_birth'));
		$profile->set('field_ordination_day', $form_state->getValue('field_ordination_day'));

		$profile->set('field_remarks', [
			'value' => $form_state->getValue('field_remarks'),
			'format' => 'basic_html',
		]);

		$profile->save();

    $response = new AjaxResponse();

    // Close modal.
    $response->addCommand(new CloseModalDialogCommand());

		$response->addCommand(new \Drupal\Core\Ajax\HtmlCommand(
			'#customer-create-message',
			'<div class="messages messages--status customer-created-success">
				Customer created successfully.
			</div>'
		));

    // Populate customer autocomplete field.
		$response->addCommand(new InvokeCommand(
			'input[name="field_customer_reference[target_id]"]',
			'val',
			[$user->getDisplayName() . ' (' . $user->id() . ')']
		));

		$response->addCommand(new InvokeCommand(
			'input[name="field_customer_reference[target_id]"]',
			'focus',
			[]
		));

    return $response;
  }

}