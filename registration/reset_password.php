<?php

declare(strict_types=1);

require '../includes/security.php';
start_secure_session();

$user_id = filter_var($_SESSION['password_reset_user_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$user_id) {
    header('Location: forgot_password.php');
    exit;
}

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Za-z]/', $password) ||
        !preg_match('/\d/', $password)
    ) {
        $error_message = 'Password must be at least 8 characters and contain a letter and a number.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        require '../database/db.php';

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $statement = $con->prepare(
            'UPDATE users
             SET password = ?
             WHERE user_id = ? AND is_verified = 1'
        );
        $statement->bind_param('si', $hashed_password, $user_id);
        $statement->execute();
        $updated = $statement->affected_rows;
        $statement->close();
        $con->close();

        if ($updated === 1) {
            unset($_SESSION['password_reset_user_id']);
            header('Location: login.php?reset=1');
            exit;
        }

        $error_message = 'Unable to reset the password. Please start the recovery process again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="Create a new secure Weblogr password.">
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <title>Choose new password · Weblogr</title>
</head>
<body>
    <main class="account-shell">
        <section class="account-page">
            <div class="account-card">
                <header class="account-card-header">
                    <div>
                        <p class="eyebrow">SECURE RECOVERY</p>
                        <h1>Choose a new password</h1>
                        <p>Create a strong password for your Weblogr account.</p>
                    </div>
                </header>

                <?php if ($error_message !== null): ?>
                    <p class="form-alert" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <div class="row">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input
                            id="reset-password"
                            type="password"
                            placeholder="New password"
                            minlength="8"
                            required
                            name="password"
                            autocomplete="new-password"
                            data-label="Password"
                        >
                    </div>

                    <div class="row">
                        <i class="fas fa-key" aria-hidden="true"></i>
                        <input
                            type="password"
                            placeholder="Confirm new password"
                            minlength="8"
                            required
                            name="confirm_password"
                            autocomplete="new-password"
                            data-label="Confirm password"
                        >
                    </div>

                    <div class="password-hint">
                        <i class="fas fa-shield-alt"></i>
                        <span>Use at least 8 characters, including a letter and a number.</span>
                    </div>

                    <div class="form-actions">
                        <button class="submit" type="submit">
                            <i class="fas fa-check"></i>
                            Update password
                        </button>
                        <a class="secondary-button" href="login.php">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
