<?php

namespace Drupal\purchase_order_notify\Plugin\MailerPolicy;

use Drupal\symfony_mailer\Plugin\EmailPolicyBase;
use Symfony\Component\Mime\Email;

/**
 * @EmailPolicy(
 *   id = "purchase_order_notify_attachment",
 *   label = @Translation("PO/Quotation Attachment Handler"),
 *   description = @Translation("Adds PDF attachments from hook_mail()."),
 *   weight = 100
 * )
 */
class AttachmentPolicy extends EmailPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function applies(array $message) {
    return !empty($message['params']['attachment']);
  }

  /**
   * {@inheritdoc}
   */
  public function alterEmail(Email $email, array $message) {

    \Drupal::logger('po_mail_debug')->info('<pre>' . print_r($message, TRUE) . '</pre>');

    if (empty($message['params']['attachment'])) {
      \Drupal::logger('po_mail_debug')->warning('Attachment param missing');
      return;
    }

    $attachment = $message['params']['attachment'];

    \Drupal::logger('po_mail_debug')->info('Attachment detected');

    $email->attach(
      $attachment['filecontent'],
      $attachment['filename'],
      $attachment['filemime']
    );
  }

}