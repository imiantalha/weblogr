<?php

declare(strict_types=1);

require '../includes/security.php';
$admin_id = require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
require '../database/db.php';

$statement = $con->prepare(
    'SELECT user_type FROM users WHERE user_id = ? LIMIT 1'
);
$statement->bind_param('i', $admin_id);
$statement->execute();
$admin = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$admin || $admin['user_type'] !== 'Admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$report_id = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$action = (string) ($_POST['action'] ?? '');

if (!$report_id || !in_array($action, ['dismiss', 'delete'], true)) {
    http_response_code(422);
    exit('Invalid moderation request.');
}

$image = null;

try {
    $con->begin_transaction();

    $statement = $con->prepare(
        'SELECT blog_id, status
         FROM reports
         WHERE report_id = ?
         FOR UPDATE'
    );
    $statement->bind_param('i', $report_id);
    $statement->execute();
    $report = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$report) {
        throw new RuntimeException('Report not found.');
    }

    if ($report['status'] !== 'pending') {
        throw new RuntimeException('Report has already been reviewed.');
    }

    $blog_id = (int) $report['blog_id'];

    if ($action === 'delete') {
        $statement = $con->prepare(
            'SELECT image
             FROM blogs
             WHERE blog_id = ?
             LIMIT 1'
        );
        $statement->bind_param('i', $blog_id);
        $statement->execute();
        $blog = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($blog !== null) {
            $image = (string) ($blog['image'] ?? '');

            $statement = $con->prepare('DELETE FROM comments WHERE blog_id = ?');
            $statement->bind_param('i', $blog_id);
            $statement->execute();
            $statement->close();

            $statement = $con->prepare('DELETE FROM post_likes WHERE blog_id = ?');
            $statement->bind_param('i', $blog_id);
            $statement->execute();
            $statement->close();

            $statement = $con->prepare('DELETE FROM blogs WHERE blog_id = ?');
            $statement->bind_param('i', $blog_id);
            $statement->execute();
            $statement->close();
        }

        $log_action = 'delete_post';
    } else {
        $log_action = 'dismiss_report';
    }

    $status = $action === 'delete' ? 'reviewed' : 'dismissed';
    $statement = $con->prepare(
        'UPDATE reports
         SET status = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE report_id = ?'
    );
    $statement->bind_param('sii', $status, $admin_id, $report_id);
    $statement->execute();
    $statement->close();

    $statement = $con->prepare(
        'INSERT INTO moderation_logs (actor_id, report_id, blog_id, action)
         VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param('iiis', $admin_id, $report_id, $blog_id, $log_action);
    $statement->execute();
    $statement->close();

    $con->commit();
    $con->close();

    if ($action === 'delete' && $image !== '') {
        $image_path = dirname(__DIR__) . '/images/' . basename($image);
        if (is_file($image_path)) {
            unlink($image_path);
        }
    }

    header('Location: reports.php');
    exit;
} catch (Throwable $exception) {
    $con->rollback();
    $con->close();
    error_log('Moderation action failed: ' . $exception->getMessage());
    http_response_code(
        $exception->getMessage() === 'Report not found.' ? 404 : 409
    );
    exit(
        $exception->getMessage() === 'Report not found.'
            ? 'Report not found.'
            : 'Unable to complete moderation action.'
    );
}
