<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

verify_csrf();
require '../database/db.php';

$blog_id = filter_var($_POST['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$blog_id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid post.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$username = strtoupper((string) $_SESSION['username']);

try {
    $con->begin_transaction();

    $statement = $con->prepare(
        'SELECT user_id
         FROM blogs
         WHERE blog_id = ?
         LIMIT 1'
    );
    $statement->bind_param('i', $blog_id);
    $statement->execute();
    $blog = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($blog === null) {
        throw new RuntimeException('Post not found.');
    }

    $statement = $con->prepare(
        'SELECT 1
         FROM post_likes
         WHERE blog_id = ? AND user_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $blog_id, $user_id);
    $statement->execute();
    $already_liked = $statement->get_result()->num_rows > 0;
    $statement->close();

    if ($already_liked) {
        $statement = $con->prepare(
            'DELETE FROM post_likes
             WHERE blog_id = ? AND user_id = ?'
        );
        $statement->bind_param('ii', $blog_id, $user_id);
        $statement->execute();
        $statement->close();

        $statement = $con->prepare(
            'UPDATE blogs
             SET likes = GREATEST(likes - 1, 0)
             WHERE blog_id = ?'
        );
        $statement->bind_param('i', $blog_id);
        $statement->execute();
        $statement->close();

        $liked = false;
    } else {
        $statement = $con->prepare(
            'INSERT IGNORE INTO post_likes (blog_id, user_id)
             VALUES (?, ?)'
        );
        $statement->bind_param('ii', $blog_id, $user_id);
        $statement->execute();
        $inserted = $statement->affected_rows === 1;
        $statement->close();

        if ($inserted) {
            $statement = $con->prepare(
                'UPDATE blogs
                 SET likes = likes + 1
                 WHERE blog_id = ?'
            );
            $statement->bind_param('i', $blog_id);
            $statement->execute();
            $statement->close();

            $blogger_id = (int) $blog['user_id'];
            if ($blogger_id !== $user_id) {
                $notification_content = "$username likes your post.";
                $statement = $con->prepare(
                    'INSERT INTO notifications (content, user_id)
                     VALUES (?, ?)'
                );
                $statement->bind_param('si', $notification_content, $blogger_id);
                $statement->execute();
                $statement->close();
            }
        }

        $liked = true;
    }

    $statement = $con->prepare(
        'SELECT likes
         FROM blogs
         WHERE blog_id = ?
         LIMIT 1'
    );
    $statement->bind_param('i', $blog_id);
    $statement->execute();
    $likes_row = $statement->get_result()->fetch_assoc();
    $statement->close();

    $likes = (int) ($likes_row['likes'] ?? 0);

    $con->commit();
    $con->close();

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likes' => $likes,
    ]);
    exit;
} catch (Throwable $exception) {
    $con->rollback();
    $con->close();
    error_log('Post like toggle failed: ' . $exception->getMessage());
    http_response_code($exception->getMessage() === 'Post not found.' ? 404 : 500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage() === 'Post not found.'
            ? 'Post not found.'
            : 'Unable to update the reaction.',
    ]);
}
