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

    // --- Diagnostic : on logue la raison exacte d'un non-envoi ---
    $missing = array();
    if (empty($SMTP_HOST)) { $missing[] = 'SMTP_HOST vide'; }
    if (empty($SMTP_USER)) { $missing[] = 'SMTP_USER vide'; }
    if (empty($SMTP_PASS)) { $missing[] = 'SMTP_PASS vide'; }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $missing[] = "adresse destinataire invalide ('$to')"; }
    if (!empty($missing)) {
        $reason = 'Envoi annule : ' . implode(', ', $missing);
        $GLOBALS['mail_last_error'] = $reason;   // lisible par l'auto-test web
        error_log('[mail] ' . $reason);
        return false;
    }
    // On masque le login pour ne pas l'exposer dans les logs : ab***@domaine.eu
    $userMasked = $SMTP_USER;
    if (strpos($SMTP_USER, '@') !== false) {
        list($u, $d) = explode('@', $SMTP_USER, 2);
        $userMasked = substr($u, 0, 2) . '***@' . $d;
    }
    error_log("[mail] Config OK (host=$SMTP_HOST, port=$SMTP_PORT, secure=$SMTP_SECURE, user=$userMasked) -> tentative d'envoi");

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        // Trace SMTP detaillee dans les logs du conteneur (connexion, auth, TLS...)
        $mail->SMTPDebug   = 2; // SMTP::DEBUG_SERVER
        $mail->Debugoutput = function ($str, $level) {
            error_log('[mail][smtp] ' . trim($str));
            if (!isset($GLOBALS['mail_debug'])) { $GLOBALS['mail_debug'] = ''; }
            $GLOBALS['mail_debug'] .= trim($str) . "\n"; // lisible par l'auto-test web
        };
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = ($SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
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
        $GLOBALS['mail_last_error'] = $mail->ErrorInfo;   // lisible par l'auto-test web
        error_log('Envoi e-mail resultats echoue: ' . $mail->ErrorInfo);
        return false;
    }
}
