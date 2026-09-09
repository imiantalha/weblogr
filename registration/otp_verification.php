<?php

declare(strict_types=1);

require '../includes/security.php';

start_secure_session();

$purpose = $_SESSION['otp_purpose'] ?? null;
$email = $_SESSION['otp_email'] ?? null;
$expires_at = (int) ($_SESSION['otp_expires_at'] ?? 0);
$resend_available_at = (int) ($_SESSION['otp_resend_available_at'] ?? 0);
$error_message = (string) ($_SESSION['otp_error'] ?? '');
unset($_SESSION['otp_error']);

if (
    !in_array($purpose, ['registration', 'password_reset'], true) ||
    !is_string($email) ||
    $expires_at < time()
) {
    $error_message = 'This verification code has expired. Please request a new code.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $digits = [];

    for ($index = 1; $index <= 6; $index++) {
        $digit = (string) ($_POST['otp' . $index] ?? '');

        if (!preg_match('/^\d$/', $digit)) {
            $error_message = 'Please enter all six OTP digits.';
            break;
        }

        $digits[] = $digit;
    }

    if ($error_message === '') {
        $entered_otp = implode('', $digits);
        require '../database/db.php';

        $verified_condition = $purpose === 'password_reset' ? 1 : 0;
        $statement = $con->prepare(
            'SELECT user_id, otp
             FROM users
             WHERE email = ? AND is_verified = ?
             LIMIT 1'
        );
        $statement->bind_param('si', $email, $verified_condition);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($user === null || !hash_equals((string) $user['otp'], $entered_otp)) {
            $error_message = 'That code is incorrect. Please check your email and try again.';
        } else {
            $user_id = (int) $user['user_id'];
            $statement = $con->prepare("UPDATE users SET otp = '' WHERE user_id = ?");
            $statement->bind_param('i', $user_id);
            $statement->execute();
            $statement->close();

            if ($purpose === 'password_reset') {
                $_SESSION['password_reset_user_id'] = $user_id;
                unset(
                    $_SESSION['otp_purpose'],
                    $_SESSION['otp_email'],
                    $_SESSION['otp_expires_at'],
                    $_SESSION['otp_resend_available_at']
                );
                $con->close();
                header('Location: reset_password.php');
                exit;
            }

            $statement = $con->prepare('UPDATE users SET is_verified = 1 WHERE user_id = ?');
            $statement->bind_param('i', $user_id);
            $statement->execute();
            $statement->close();
            $con->close();

            unset(
                $_SESSION['otp_purpose'],
                $_SESSION['otp_email'],
                $_SESSION['otp_expires_at'],
                $_SESSION['otp_resend_available_at']
            );

            header('Location: success.php');
            exit;
        }

        $con->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="Verify your Weblogr email address.">
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <title>Verify email · Weblogr</title>
</head>
<body>
    <main class="otp-page">
        <section class="otp-card" aria-labelledby="otp-title">
            <div class="otp-icon">
                <i class="fas fa-shield-alt"></i>
            </div>

            <p class="eyebrow">SECURE VERIFICATION</p>
            <h1 id="otp-title">Check your email</h1>
            <p class="otp-description">
                We sent a 6-digit code to the email address below. Enter it to continue securely.
            </p>

            <div class="otp-email">
                <i class="fas fa-envelope"></i>
                <span><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if ($error_message !== ''): ?>
                <p class="form-alert otp-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form method="post" novalidate id="otp-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="otp-inputs" role="group" aria-label="Six digit verification code">
                    <?php for ($index = 1; $index <= 6; $index++): ?>
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]"
                            name="otp<?= $index ?>"
                            class="otp-digit"
                            maxlength="1"
                            required
                            autocomplete="one-time-code"
                            aria-label="OTP digit <?= $index ?>"
                        >
                    <?php endfor; ?>
                </div>

                <button type="submit" class="otp-button">
                    Verify code
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="otp-meta">
                <span>Didn't receive it?</span>
                <a
                    href="resend_otp.php"
                    class="resend-otp <?= $resend_available_at > time() ? 'disabled' : '' ?>"
                    id="resend-link"
                >
                    Resend code
                </a>
            </div>

            <p class="otp-expiry" id="otp-expiry" data-expires="<?= $expires_at ?>"></p>
            <p class="otp-hint">Never share this code with anyone. It expires after 10 minutes.</p>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = [...document.querySelectorAll('.otp-digit')];

            inputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);

                    if (input.value && inputs[index + 1]) {
                        inputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace' && !input.value && inputs[index - 1]) {
                        inputs[index - 1].focus();
                    }

                    if (event.key === 'ArrowLeft' && inputs[index - 1]) {
                        inputs[index - 1].focus();
                    }

                    if (event.key === 'ArrowRight' && inputs[index + 1]) {
                        inputs[index + 1].focus();
                    }
                });

                input.addEventListener('paste', (event) => {
                    const text = (event.clipboardData || window.clipboardData)
                        .getData('text')
                        .replace(/\D/g, '')
                        .slice(0, 6);

                    if (!text) {
                        return;
                    }

                    event.preventDefault();

                    text.split('').forEach((digit, digitIndex) => {
                        if (inputs[digitIndex]) {
                            inputs[digitIndex].value = digit;
                        }
                    });

                    inputs[Math.min(text.length, 6) - 1]?.focus();
                });
            });

            const expiry = document.getElementById('otp-expiry');
            const expiresAt = Number(expiry?.dataset.expires || 0);

            const tick = () => {
                const remaining = Math.max(
                    0,
                    expiresAt - Math.floor(Date.now() / 1000)
                );

                if (!remaining) {
                    expiry.textContent = 'This code has expired. Request a new one.';
                    return;
                }

                expiry.textContent =
                    'Code expires in ' +
                    Math.floor(remaining / 60) +
                    ':' +
                    String(remaining % 60).padStart(2, '0');

                setTimeout(tick, 1000);
            };

            tick();
        });
    </script>
</body>
</html>
