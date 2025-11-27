<?php

namespace Drupal\purchase_order_notify\Plugin\MailerPolicy;

use Drupal\Core\Mail\MailFormatHelper;
use Drupal\symfony_mailer\Plugin\EmailPolicyBase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @MailerPolicy(
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
    // Apply when attachment exists.
    return !empty($message['params']['attachment']);
  }

  /**
   * {@inheritdoc}
   */
  public function alterEmail(Email $email, array $message) {
    if (empty($message['params']['attachment'])) {
      return;
    }

    $attachment = $message['params']['attachment'];

    $file_content = $attachment['filecontent'] ?? NULL;
    $filename     = $attachment['filename'] ?? 'attachment.pdf';
    $filemime     = $attachment['filemime'] ?? 'application/pdf';

    if ($file_content) {
      // Add as PDF attachment.
      $email->attach(
        $file_content,
        $filename,
        $filemime
      );
    }
  }

}