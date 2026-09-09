<?php

declare(strict_types=1);

require '../includes/security.php';
require '../includes/google_auth.php';

start_secure_session();

if (isset($_SESSION['user_id'])) {
    header('Location: ../posts/index.php');
    exit;
}

$login_error = false;
$google_error = (string) ($_SESSION['google_auth_error'] ?? '');
unset($_SESSION['google_auth_error']);
$logged_out = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
$password_reset = isset($_GET['reset']) && $_GET['reset'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require '../database/db.php';

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    enforce_login_rate_limit($username);

    if ($username === '' || $password === '') {
        $login_error = true;
    } else {
        $statement = $con->prepare(
            'SELECT username, password, is_verified, user_type, user_id
             FROM users
             WHERE username = ?
             LIMIT 1'
        );
        $statement->bind_param('s', $username);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (
            $user !== null &&
            (int) $user['is_verified'] === 1 &&
            password_verify($password, $user['password'])
        ) {
            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            clear_login_rate_limit($username);
            $con->close();

            header('Location: ../posts/index.php');
            exit;
        }

        $login_error = true;
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
    <meta
        name="description"
        content="Sign in to Weblogr and continue reading, writing and connecting."
    >
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"
    >
    <script src="form-validation.js" defer></script>
    <title>Sign in · Weblogr</title>
</head>
<body>
    <main class="account-shell">
        <section class="account-page">
            <div class="account-card">
                <header class="account-card-header">
                    <div class="header-copy">
                        <p class="eyebrow">WELCOME BACK</p>
                        <h1>Sign in to Weblogr</h1>
                        <p>Continue discovering ideas and sharing stories with the community.</p>
                    </div>
                </header>

                <?php if ($logged_out): ?>
                    <p class="form-success" role="status">
                        <i class="fas fa-check-circle"></i>
                        You have been safely signed out.
                    </p>
                <?php elseif ($password_reset): ?>
                    <p class="form-success" role="status">
                        <i class="fas fa-check-circle"></i>
                        Your password has been updated. You can sign in now.
                    </p>
                <?php endif; ?>

                <?php if ($login_error): ?>
                    <p class="form-alert" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        Username or password is incorrect, or the account is not verified.
                    </p>
                <?php endif; ?>

                <?php if ($google_error !== ''): ?>
                    <p class="form-alert" role="alert">
                        <?= htmlspecialchars($google_error, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <?php if ($google_enabled): ?>
                    <a class="google-login" href="google_start.php">
                        <span class="google-mark" aria-hidden="true">G</span>
                        <span>Continue with Google</span>
                    </a>

                    <div class="auth-divider" aria-hidden="true">
                        <span>or sign in with username</span>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <div class="row">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input
                            type="text"
                            placeholder="Username"
                            required
                            name="username"
                            autocomplete="username"
                            data-label="Username"
                            aria-label="Username"
                        >
                    </div>

                    <div class="row">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input
                            id="login-password"
                            type="password"
                            placeholder="Password"
                            required
                            name="password"
                            autocomplete="current-password"
                            data-label="Password"
                            aria-label="Password"
                        >
                    </div>

                    <div class="form-actions">
                        <button class="submit" type="submit">
                            <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                            Sign in
                        </button>
                        <a class="secondary-button" href="forgot_password.php">Forgot password?</a>
                    </div>
                </form>

                <p class="signup-link" style="margin-top: 20px">
                    New to Weblogr? <a href="signup.php">Create an account</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
