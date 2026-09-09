<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();
require '../includes/view_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    render_empty_state(
        'Action not allowed',
        'Comments can only be submitted from a Weblogr story.',
        'Back to Discover',
        '../posts/index.php',
        'fa-ban'
    );
}

verify_csrf();
require '../database/db.php';

$blog_id = filter_var($_POST['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$commenter_id = (int) $_SESSION['user_id'];
$comment_text = trim((string) ($_POST['comment_text'] ?? ''));
$username = strtoupper((string) ($_SESSION['username'] ?? ''));

if (!$blog_id || $comment_text === '' || mb_strlen($comment_text) > 2000) {
    $con->close();
    http_response_code(422);
    render_empty_state(
        'Comment needs attention',
        'Please enter a comment between 1 and 2,000 characters.',
        'Back to story',
        'comments.php?blog_id=' . max(1, (int) $blog_id),
        'fa-comment'
    );
}

try {
    $con->begin_transaction();

    $select = $con->prepare(
        'SELECT user_id
         FROM blogs
         WHERE blog_id = ?
         LIMIT 1'
    );
    $select->bind_param('i', $blog_id);
    $select->execute();
    $blog = $select->get_result()->fetch_assoc();
    $select->close();

    if ($blog === null) {
        throw new RuntimeException('Post not found.');
    }

    $insert = $con->prepare(
        'INSERT INTO comments (blog_id, commenter_id, comment_text)
         VALUES (?, ?, ?)'
    );
    $insert->bind_param('iis', $blog_id, $commenter_id, $comment_text);
    $insert->execute();
    $insert->close();

    $notification_content = "$username commented on your post.";
    $notification = $con->prepare(
        'INSERT INTO notifications (content, user_id)
         VALUES (?, ?)'
    );
    $post_owner_id = (int) $blog['user_id'];

    if ($post_owner_id !== $commenter_id) {
        $notification->bind_param('si', $notification_content, $post_owner_id);
        $notification->execute();
    }

    $notification->close();
    $con->commit();
    $con->close();

    header('Location: comments.php?blog_id=' . $blog_id);
    exit;
} catch (Throwable $exception) {
    $con->rollback();
    $con->close();
    error_log('Comment save failed: ' . $exception->getMessage());

    http_response_code($exception->getMessage() === 'Post not found.' ? 404 : 500);
    render_empty_state(
        $exception->getMessage() === 'Post not found.' ? 'Story unavailable' : 'Comment could not be posted',
        $exception->getMessage() === 'Post not found.'
            ? 'This story is no longer available.'
            : 'Something went wrong while posting your comment. Please try again.',
        'Back to story',
        'comments.php?blog_id=' . $blog_id,
        $exception->getMessage() === 'Post not found.' ? 'fa-search' : 'fa-comment'
    );
}
