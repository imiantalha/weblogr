<?php

declare(strict_types=1);

require '../includes/security.php';
require '../includes/google_auth.php';

start_secure_session();

if (isset($_SESSION['user_id'])) {
    header('Location: ../posts/index.php');
    exit;
}

$registration_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require '../database/db.php';

    $first_name = trim((string) ($_POST['first_name'] ?? ''));
    $last_name = trim((string) ($_POST['last_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (
        $first_name === '' ||
        mb_strlen($first_name) > 50 ||
        $last_name === '' ||
        mb_strlen($last_name) > 50
    ) {
        $registration_error = 'Please enter a valid first and last name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,255}$/', $username)) {
        $registration_error = 'Username must be 3-255 characters and contain only letters, numbers, dots, underscores, or hyphens.';
    } elseif (strlen($password) < 8) {
        $registration_error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $registration_error = 'Passwords do not match.';
    } else {
        $statement = $con->prepare(
            'SELECT username, email, is_verified
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1'
        );
        $statement->bind_param('ss', $username, $email);
        $statement->execute();
        $existing_user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($existing_user !== null) {
            $registration_error = 'That username or email is not available. Try signing in, or use a different username and email.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $statement = $con->prepare(
                'INSERT INTO users (first_name, last_name, username, email, password, otp, is_verified)
                 VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            $statement->bind_param(
                'ssssss',
                $first_name,
                $last_name,
                $username,
                $email,
                $password_hash,
                $otp
            );
            $statement->execute();
            $statement->close();
            $con->close();

            $_SESSION['otp_purpose'] = 'registration';
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_expires_at'] = time() + 600;
            $_SESSION['otp_resend_available_at'] = time() + 30;

            require 'mail.php';
            exit;
        }
    }

    $con->close();
}

$google_enabled = google_oauth_configured();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="Create your Weblogr account and start sharing ideas.">
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"
    >
    <script src="form-validation.js" defer></script>
    <title>Create account · Weblogr</title>
</head>
<body>
    <main class="account-shell">
        <section class="account-page">
            <div class="account-card">
                <header class="account-card-header">
                    <div class="header-copy">
                        <p class="eyebrow">JOIN WEBLOGR</p>
                        <h1>Create your account</h1>
                        <p>Build your writer profile and start sharing ideas with the community.</p>
                    </div>
                </header>

                <?php if ($registration_error !== null): ?>
                    <p class="form-alert" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($registration_error, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <?php if ($google_enabled): ?>
                    <a class="google-login" href="google_start.php">
                        <span class="google-mark" aria-hidden="true">G</span>
                        <span>Continue with Google</span>
                    </a>

                    <div class="auth-divider" aria-hidden="true">
                        <span>or create with email</span>
                    </div>
                <?php endif; ?>

                <form onsubmit="return form_validation()" method="post" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <div class="name-grid">
                        <div class="row">
                            <i class="fas fa-user" aria-hidden="true"></i>
                            <input
                                type="text"
                                placeholder="First name"
                                required
                                name="first_name"
                                maxlength="50"
                                autocomplete="given-name"
                                data-label="First name"
                            >
                        </div>
                        <div class="row">
                            <i class="fas fa-user" aria-hidden="true"></i>
                            <input
                                type="text"
                                placeholder="Last name"
                                required
                                name="last_name"
                                maxlength="50"
                                autocomplete="family-name"
                                data-label="Last name"
                            >
                        </div>
                    </div>

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

                    <div class="row">
                        <i class="fas fa-at" aria-hidden="true"></i>
                        <input
                            type="text"
                            placeholder="Username"
                            required
                            name="username"
                            minlength="3"
                            maxlength="255"
                            autocomplete="username"
                            data-label="Username"
                        >
                    </div>

                    <div class="row">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input
                            type="password"
                            placeholder="Password"
                            required
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            data-label="Password"
                        >
                    </div>

                    <div class="row">
                        <i class="fas fa-key" aria-hidden="true"></i>
                        <input
                            type="password"
                            placeholder="Confirm password"
                            required
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            data-label="Confirm password"
                        >
                    </div>

                    <div class="password-hint">
                        <i class="fas fa-shield-alt"></i>
                        <span>Use at least 8 characters. Your password is securely hashed before storage.</span>
                    </div>

                    <div class="row button">
                        <input
                            class="submit"
                            type="submit"
                            value="Create account"
                            name="signup"
                        >
                    </div>

                    <p class="signup-link">
                        Already have an account? <a href="login.php">Sign in</a>
                    </p>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
