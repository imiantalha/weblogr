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

if (!google_oauth_configured()) {
    http_response_code(503);
    exit('Google Sign-In is not configured.');
}

$next = (string) ($_GET['next'] ?? '../posts/index.php');

if (
    $next === '' ||
    !str_starts_with($next, '../') ||
    str_contains($next, '://') ||
    str_contains($next, "\0")
) {
    $next = '../posts/index.php';
}

$state = bin2hex(random_bytes(32));
$verifier = google_oauth_base64url(random_bytes(48));

$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_verifier'] = $verifier;
$_SESSION['google_oauth_next'] = $next;
$_SESSION['google_oauth_started_at'] = time();

$config = google_oauth_config();

header(
    'Location: ' . google_oauth_authorization_url(
        $config,
        $state,
        google_oauth_code_challenge($verifier)
    ),
    true,
    302
);
exit;
