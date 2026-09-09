<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
require '../database/db.php';

$follower_id = (int) $_SESSION['user_id'];
$username = strtoupper((string) $_SESSION['username']);
$user_id = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$user_id || $user_id === $follower_id) {
    $con->close();
    http_response_code(422);
    exit('Invalid user.');
}

try {
    $con->begin_transaction();

    $target = $con->prepare(
        'SELECT user_id FROM users WHERE user_id = ? LIMIT 1'
    );
    $target->bind_param('i', $user_id);
    $target->execute();
    $target_exists = $target->get_result()->num_rows === 1;
    $target->close();

    if (!$target_exists) {
        throw new RuntimeException('User not found.');
    }

    $follow = $con->prepare(
        'INSERT IGNORE INTO followers (blogger_id, follower_id)
         VALUES (?, ?)'
    );
    $follow->bind_param('ii', $user_id, $follower_id);
    $follow->execute();
    $inserted = $follow->affected_rows === 1;
    $follow->close();

    if ($inserted) {
        $notification_content = "$username started following you.";
        $notification = $con->prepare(
            'INSERT INTO notifications (content, user_id)
             VALUES (?, ?)'
        );
        $notification->bind_param('si', $notification_content, $user_id);
        $notification->execute();
        $notification->close();
    }

    $con->commit();
    $con->close();
    header('Location: blog_poster.php?user_id=' . $user_id);
    exit;
} catch (Throwable $exception) {
    $con->rollback();
    $con->close();
    error_log('Follow failed: ' . $exception->getMessage());
    http_response_code($exception->getMessage() === 'User not found.' ? 404 : 500);
    echo $exception->getMessage() === 'User not found.'
        ? 'User not found.'
        : 'Unable to follow the user.';
}
