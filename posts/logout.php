<?php

declare(strict_types=1);

require '../includes/security.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    render_product_error(
        'Invalid logout request',
        'For security, please use the Log out button from your Weblogr account menu.',
        405
    );
}

verify_csrf();
require '../database/db.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ../registration/login.php?logged_out=1');
exit;
