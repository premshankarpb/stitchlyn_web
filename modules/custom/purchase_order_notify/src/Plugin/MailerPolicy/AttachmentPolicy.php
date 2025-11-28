<?php

namespace Drupal\purchase_order_notify\Plugin\MailerPolicy;

use Drupal\symfony_mailer\Plugin\EmailPolicyBase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @EmailPolicy(
 *   id = "purchase_order_notify_attachment",
 *   label = @Translation("PO/Quotation Attachment Handler"),
 *   description = @Translation("Adds PDF attachments from mail params."),
 *   weight = 100
 * )
 */
class AttachmentPolicy extends EmailPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function applies(array $message) {

    // Debug to confirm plugin activation
    \Drupal::logger('po_mail_debug')->info('AttachmentPolicy::applies triggered');

    return !empty($message['params']['attachment']);
  }

  /**
   * {@inheritdoc}
   */
  public function alterEmail(Email $email, array $message) {

    \Drupal::logger('po_mail_debug')->info('<pre>'. print_r($message['params'], TRUE) .'</pre>');

    if (empty($message['params']['attachment'])) {
      \Drupal::logger('po_mail_debug')->warning('Attachment missing in params');
      return;
    }

    $file = $message['params']['attachment'];

    $email->addPart(new DataPart(
      $file['filecontent'],      // binary PDF
      $file['filename'],         // e.g. po-19.pdf
      $file['filemime']          // application/pdf
    ));

    \Drupal::logger('po_mail_debug')->info('PDF attachment added successfully.');
  }
}