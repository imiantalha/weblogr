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

$comment_id = filter_var($_POST['comment_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$blog_id = filter_var($_POST['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$user_id = (int) $_SESSION['user_id'];
$username = strtoupper((string) ($_SESSION['username'] ?? ''));

if (!$comment_id || !$blog_id) {
    $con->close();
    http_response_code(422);
    exit('Invalid comment or post ID.');
}

try {
    $con->begin_transaction();

    $statement = $con->prepare(
        'SELECT commenter_id
         FROM comments
         WHERE comment_id = ? AND blog_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $comment_id, $blog_id);
    $statement->execute();
    $comment = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($comment === null) {
        throw new RuntimeException('Comment not found.');
    }

    $statement = $con->prepare(
        'SELECT 1
         FROM comment_likes
         WHERE comment_id = ? AND user_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $comment_id, $user_id);
    $statement->execute();
    $already_liked = $statement->get_result()->num_rows > 0;
    $statement->close();

    if (!$already_liked) {
        $statement = $con->prepare(
            'INSERT IGNORE INTO comment_likes (comment_id, user_id)
             VALUES (?, ?)'
        );
        $statement->bind_param('ii', $comment_id, $user_id);
        $statement->execute();
        $inserted = $statement->affected_rows === 1;
        $statement->close();

        if ($inserted) {
            $statement = $con->prepare(
                'UPDATE comments
                 SET likes = likes + 1
                 WHERE comment_id = ?'
            );
            $statement->bind_param('i', $comment_id);
            $statement->execute();
            $statement->close();

            $commenter_id = (int) $comment['commenter_id'];
            if ($commenter_id !== $user_id) {
                $notification_content = "$username liked your comment.";
                $statement = $con->prepare(
                    'INSERT INTO notifications (content, user_id)
                     VALUES (?, ?)'
                );
                $statement->bind_param('si', $notification_content, $commenter_id);
                $statement->execute();
                $statement->close();
            }
        }
    }

    $con->commit();
    $con->close();

    header('Location: comments.php?blog_id=' . $blog_id);
    exit;
} catch (Throwable $exception) {
    $con->rollback();
    $con->close();
    error_log('Comment like failed: ' . $exception->getMessage());
    http_response_code($exception->getMessage() === 'Comment not found.' ? 404 : 500);
    echo $exception->getMessage() === 'Comment not found.'
        ? 'Comment not found.'
        : 'Unable to like the comment.';
}
