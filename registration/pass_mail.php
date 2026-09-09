<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!isset($email, $otp)) {
    throw new RuntimeException('Password reset email requires a recipient and OTP.');
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('MAIL_USERNAME') ?: '';
    $mail->Password = getenv('MAIL_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) (getenv('MAIL_PORT') ?: 587);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $mail->Username;
    $fromName = getenv('MAIL_FROM_NAME') ?: 'Weblogr';

    if ($mail->Username === '' || $mail->Password === '' || $fromAddress === '') {
        throw new RuntimeException('Mail credentials are not configured.');
    }

    $safeOtp = htmlspecialchars((string) $otp, ENT_QUOTES, 'UTF-8');

    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($email);
    $mail->Subject = 'Your Weblogr password reset code';
    $mail->Body = '<!doctype html><html><body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#172033"><div style="padding:40px 16px"><div style="max-width:520px;margin:auto;background:#fff;border:1px solid #e6eaf0;border-radius:20px;overflow:hidden"><div style="padding:26px 30px;background:#101828;color:#fff"><div style="font-size:24px;font-weight:800">Weblogr</div><div style="margin-top:5px;color:#cbd5e1;font-size:13px">Ideas worth sharing</div></div><div style="padding:34px 30px"><div style="display:inline-block;padding:7px 10px;background:#fff4e5;color:#b54708;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.08em">PASSWORD RESET</div><h1 style="font-size:26px;margin:18px 0 10px">Reset your password</h1><p style="font-size:15px;line-height:1.7;color:#697386;margin:0 0 24px">Enter the verification code below to continue resetting your Weblogr password. This code expires in 10 minutes.</p><div style="text-align:center;margin:28px 0"><span style="display:inline-block;padding:18px 28px;background:#f5f7fb;border:1px solid #e6eaf0;border-radius:14px;font-size:32px;letter-spacing:9px;font-weight:800;color:#172033">' . $safeOtp . '</span></div><p style="font-size:12px;line-height:1.6;color:#98a2b3;margin:0">If you did not request a password reset, ignore this email and make sure your account password remains secure.</p></div><div style="padding:18px 30px;background:#f8fafc;border-top:1px solid #e6eaf0;color:#98a2b3;font-size:11px">This is an automated message from Weblogr. Please do not reply to this email.</div></div></div></body></html>';
    $mail->AltBody = 'Your Weblogr password reset code is ' . $otp . '. It expires in 10 minutes.';
    $mail->send();

    header('Location: otp_verification.php');
    exit;
} catch (Throwable $exception) {
    error_log('Password reset email failed: ' . $exception->getMessage());
    $_SESSION['otp_error'] = 'We could not send the reset email. Please try again.';
    header('Location: otp_verification.php');
    exit;
}
