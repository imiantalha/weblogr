<?php

declare(strict_types=1);

require '../includes/security.php';
start_secure_session();
require '../database/db.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    enforce_rate_limit('password-recovery', $email, 3, 900);

    $message = 'If the email is registered, a reset code will be sent.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $con->prepare(
            'SELECT user_id, is_verified
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $statement->bind_param('s', $email);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($user !== null && (int) $user['is_verified'] === 1) {
            $otp = (string) random_int(100000, 999999);
            $user_id = (int) $user['user_id'];

            $statement = $con->prepare('UPDATE users SET otp = ? WHERE user_id = ?');
            $statement->bind_param('si', $otp, $user_id);
            $statement->execute();
            $statement->close();

            $_SESSION['otp_purpose'] = 'password_reset';
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_expires_at'] = time() + 600;
            $_SESSION['otp_resend_available_at'] = time() + 30;

            $con->close();
            require 'pass_mail.php';
            exit;
        }
    }
}

$con->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="Request a secure Weblogr password reset code.">
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <title>Forgot password · Weblogr</title>
</head>
<body>
    <main class="account-shell">
        <section class="account-page">
            <div class="account-card">
                <header class="account-card-header">
                    <div>
                        <p class="eyebrow">ACCOUNT RECOVERY</p>
                        <h1>Reset your password</h1>
                        <p>
                            Enter your email and we'll send a secure verification code if an account is registered.
                        </p>
                    </div>
                </header>

                <?php if ($message !== null): ?>
                    <p class="form-success" role="status">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <?php if ($error !== null): ?>
                    <p class="form-alert" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <div class="row">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <input
                            type="email"
                            placeholder="Email address"
                            required
                            name="email"
                            autocomplete="email"
                            data-label="Email"
                        >
                    </div>

                    <div class="form-actions">
                        <button class="submit" type="submit">
                            <i class="fas fa-paper-plane"></i>
                            Send reset code
                        </button>
                        <a class="secondary-button" href="login.php">Back to sign in</a>
                    </div>
                </form>

                <p class="signup-link" style="margin-top:20px">
                    Remember your password? <a href="login.php">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
