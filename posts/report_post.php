<?php

declare(strict_types=1);

require '../includes/security.php';
$user_id = require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
require '../database/db.php';

$blog_id = filter_var($_POST['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$reason = trim((string) ($_POST['reason'] ?? ''));
$details = trim((string) ($_POST['details'] ?? ''));
$allowed = [
    'spam',
    'harassment',
    'hate or abuse',
    'misinformation',
    'copyright',
    'other',
];

if (
    !$blog_id ||
    !in_array($reason, $allowed, true) ||
    mb_strlen($details) > 2000
) {
    http_response_code(422);
    exit('Please provide valid report details.');
}

$statement = $con->prepare(
    'SELECT user_id
     FROM blogs
     WHERE blog_id = ?
     LIMIT 1'
);
$statement->bind_param('i', $blog_id);
$statement->execute();
$post = $statement->get_result()->fetch_assoc();
$statement->close();

if ($post === null) {
    $con->close();
    http_response_code(404);
    exit('Post not found.');
}

if ((int) $post['user_id'] === $user_id) {
    $con->close();
    http_response_code(422);
    exit('You cannot report your own post.');
}

$statement = $con->prepare(
    "SELECT report_id
     FROM reports
     WHERE blog_id = ? AND reporter_id = ? AND status = 'pending'
     LIMIT 1"
);
$statement->bind_param('ii', $blog_id, $user_id);
$statement->execute();
$duplicate = $statement->get_result()->num_rows === 1;
$statement->close();

if ($duplicate) {
    $con->close();
    header('Location: index.php?report=already-submitted');
    exit;
}

$statement = $con->prepare(
    'INSERT INTO reports (blog_id, reporter_id, reason, details)
     VALUES (?, ?, ?, ?)'
);
$statement->bind_param('iiss', $blog_id, $user_id, $reason, $details);
$statement->execute();
$statement->close();
$con->close();

header('Location: index.php?report=submitted');
exit;
