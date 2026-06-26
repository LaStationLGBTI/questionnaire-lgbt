<?php
// Helper d'envoi des résultats par e-mail via PHPMailer + SMTP.
// Ne lève jamais d'exception vers l'appelant : renvoie true si parti, false sinon.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';

function send_results_email($to, $subject, $htmlBody) {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_PASS, $SMTP_FROM, $SMTP_FROM_NAME;

    // Pas de configuration SMTP, ou adresse invalide -> on n'envoie rien (le site continue normalement)
    if (empty($SMTP_HOST) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = $SMTP_USER === '' ? false : true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = $SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : $SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '';
        $mail->Port       = (int) $SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($SMTP_FROM, $SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        // Version texte : on retire d'abord le bloc <style> pour ne pas le voir en clair
        $plain = preg_replace('#<style.*?</style>#is', '', $htmlBody);
        $mail->AltBody = trim(preg_replace('/[ \t]+/', ' ', strip_tags($plain)));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Envoi e-mail resultats echoue: ' . $mail->ErrorInfo);
        return false;
    }
}
