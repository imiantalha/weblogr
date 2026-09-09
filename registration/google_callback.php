<?php

declare(strict_types=1);

require '../includes/security.php';
require '../includes/google_auth.php';

start_secure_session();
header('Cache-Control: no-store, max-age=0');

if (isset($_SESSION['user_id'])) {
    header('Location: ../posts/index.php');
    exit;
}

function google_auth_fail(string $message): never
{
    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_verifier'],
        $_SESSION['google_oauth_next'],
        $_SESSION['google_oauth_started_at']
    );

    $_SESSION['google_auth_error'] = $message;
    header('Location: login.php');
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$expected = (string) ($_SESSION['google_oauth_state'] ?? '');
$started = (int) ($_SESSION['google_oauth_started_at'] ?? 0);
$verifier = (string) ($_SESSION['google_oauth_verifier'] ?? '');
$next = (string) ($_SESSION['google_oauth_next'] ?? '../posts/index.php');

if (
    $state === '' ||
    $expected === '' ||
    !hash_equals($expected, $state) ||
    $verifier === '' ||
    $started < time() - 600
) {
    google_auth_fail('Your Google sign-in session expired. Please try again.');
}

if (isset($_GET['error'])) {
    google_auth_fail('Google sign-in was cancelled or could not be completed.');
}

$code = (string) ($_GET['code'] ?? '');

if ($code === '') {
    google_auth_fail('Google did not return an authorization code.');
}

$config = google_oauth_config();

try {
    [$status, $token] = google_oauth_http(
        'https://oauth2.googleapis.com/token',
        [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]
    );

    if ($status < 200 || $status >= 300 || empty($token['access_token'])) {
        throw new RuntimeException('Token exchange failed.');
    }

    [$status, $google] = google_oauth_http(
        'https://openidconnect.googleapis.com/v1/userinfo',
        [],
        [
            'Accept: application/json',
            'Authorization: Bearer ' . $token['access_token'],
        ]
    );

    if (
        $status < 200 ||
        $status >= 300 ||
        !isset($google['sub'], $google['email'])
    ) {
        throw new RuntimeException('User information was unavailable.');
    }
} catch (Throwable $exception) {
    google_auth_fail('Google sign-in could not be completed. Please try again.');
}

if (
    empty($google['email_verified']) ||
    !filter_var($google['email'], FILTER_VALIDATE_EMAIL)
) {
    google_auth_fail('Google did not provide a verified email address.');
}

require '../database/db.php';

$googleId = trim((string) $google['sub']);
$email = strtolower(trim((string) $google['email']));
$firstName = mb_substr(trim((string) ($google['given_name'] ?? '')), 0, 50);
$lastName = mb_substr(trim((string) ($google['family_name'] ?? '')), 0, 50);

if ($firstName === '') {
    $full = trim((string) ($google['name'] ?? 'Google User'));
    $parts = preg_split('/\s+/', $full, 2);
    $firstName = mb_substr($parts[0] ?? 'Google', 0, 50);
    $lastName = mb_substr($parts[1] ?? 'User', 0, 50);
}

if ($lastName === '') {
    $lastName = 'User';
}

$statement = $con->prepare(
    'SELECT user_id, username, user_type
     FROM users
     WHERE google_id = ?
     LIMIT 1'
);
$statement->bind_param('s', $googleId);
$statement->execute();
$user = $statement->get_result()->fetch_assoc();
$statement->close();

if ($user === null) {
    $statement = $con->prepare(
        'SELECT user_id, username, user_type
         FROM users
         WHERE email = ?
         LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($user !== null) {
        $statement = $con->prepare(
            'UPDATE users
             SET google_id = ?, is_verified = 1, first_name = ?, last_name = ?
             WHERE user_id = ?'
        );
        $statement->bind_param('sssi', $googleId, $firstName, $lastName, $user['user_id']);
        $statement->execute();
        $statement->close();
    }
}

if ($user === null) {
    $base = preg_replace(
        '/[^A-Za-z0-9_.-]/',
        '',
        (string) strtok($email, '@')
    ) ?: 'googleuser';
    $username = substr($base, 0, 240);
    $found = false;

    for ($suffix = 0; $suffix < 1000; $suffix++) {
        $candidate = $suffix === 0
            ? $username
            : substr($base, 0, 230) . '-' . $suffix;

        $statement = $con->prepare(
            'SELECT user_id
             FROM users
             WHERE username = ?
             LIMIT 1'
        );
        $statement->bind_param('s', $candidate);
        $statement->execute();
        $exists = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($exists === null) {
            $username = $candidate;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $con->close();
        google_auth_fail('We could not create a unique username for your Google account.');
    }

    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $statement = $con->prepare(
        "INSERT INTO users (first_name, last_name, username, email, password, otp, is_verified, user_type, google_id)
         VALUES (?, ?, ?, ?, ?, '', 1, 'Common user', ?)"
    );

    if (!$statement) {
        $con->close();
        google_auth_fail('We could not create your Weblogr account right now. Please try again.');
    }

    $statement->bind_param(
        'ssssss',
        $firstName,
        $lastName,
        $username,
        $email,
        $password,
        $googleId
    );

    if (!$statement->execute()) {
        $statement->close();
        $con->close();
        google_auth_fail('We could not create your Weblogr account right now. Please try again.');
    }

    $newId = $con->insert_id;
    $statement->close();

    $user = [
        'user_id' => $newId,
        'username' => $username,
        'user_type' => 'Common user',
    ];
}

$con->close();

unset(
    $_SESSION['google_oauth_state'],
    $_SESSION['google_oauth_verifier'],
    $_SESSION['google_oauth_next'],
    $_SESSION['google_oauth_started_at']
);

session_regenerate_id(true);
$_SESSION['username'] = $user['username'];
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if (
    $next === '' ||
    !str_starts_with($next, '../') ||
    str_contains($next, '://') ||
    str_contains($next, "\0")
) {
    $next = '../posts/index.php';
}

header('Location: ' . $next);
exit;
