<?php

declare(strict_types=1);

require '../includes/security.php';
$user_id = require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_all'])) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
require '../database/db.php';

$statement = $con->prepare('DELETE FROM notifications WHERE user_id = ?');
$statement->bind_param('i', $user_id);
$statement->execute();
$statement->close();
$con->close();

header('Location: notifications.php');
exit;
