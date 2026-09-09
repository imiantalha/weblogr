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
        'Use the controls inside Weblogr to manage your content.',
        'Back to Discover',
        'index.php',
        'fa-ban'
    );
}

verify_csrf();
require '../database/db.php';

$user_id = (int) $_SESSION['user_id'];
$blog_id = filter_var($_POST['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$draft_id = filter_var($_POST['draft_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

$statement = $con->prepare(
    'SELECT user_type FROM users WHERE user_id = ? LIMIT 1'
);
$statement->bind_param('i', $user_id);
$statement->execute();
$user = $statement->get_result()->fetch_assoc();
$statement->close();

if ($user === null) {
    $con->close();
    http_response_code(403);
    render_empty_state(
        'Access denied',
        'Your account could not be verified for this action.',
        'Back to Discover',
        'index.php',
        'fa-lock'
    );
}

$is_admin = $user['user_type'] === 'Admin';

if ($blog_id) {
    $image = null;

    try {
        $con->begin_transaction();

        $statement = $con->prepare(
            'SELECT image, user_id
             FROM blogs
             WHERE blog_id = ?
             LIMIT 1'
        );
        $statement->bind_param('i', $blog_id);
        $statement->execute();
        $post = $statement->get_result()->fetch_assoc();
        $statement->close();

        if (
            $post === null ||
            (!$is_admin && (int) $post['user_id'] !== $user_id)
        ) {
            throw new RuntimeException('Post not found or access denied.');
        }

        $image = (string) ($post['image'] ?? '');

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
        $deleted = $statement->affected_rows;
        $statement->close();

        if ($deleted !== 1) {
            throw new RuntimeException('Post not found or access denied.');
        }

        $con->commit();
        $con->close();

        if ($image !== '') {
            $image_path = dirname(__DIR__) . '/images/' . basename($image);
            if (is_file($image_path)) {
                unlink($image_path);
            }
        }

        header('Location: ' . ($is_admin ? 'manage_content.php' : 'user_posts.php'));
        exit;
    } catch (Throwable $exception) {
        $con->rollback();
        $con->close();
        error_log('Delete post failed: ' . $exception->getMessage());
        http_response_code(
            $exception->getMessage() === 'Post not found or access denied.' ? 404 : 500
        );
        render_empty_state(
            $exception->getMessage() === 'Post not found or access denied.'
                ? 'Post unavailable'
                : 'Could not delete the post',
            $exception->getMessage() === 'Post not found or access denied.'
                ? 'The post was removed, no longer exists, or you do not have permission to delete it.'
                : 'Something went wrong while deleting the post. Please try again.',
            'Back to posts',
            $is_admin ? 'manage_content.php' : 'user_posts.php',
            $exception->getMessage() === 'Post not found or access denied.' ? 'fa-search' : 'fa-trash'
        );
    }
}

if ($draft_id) {
    $image = null;

    $statement = $con->prepare(
        'SELECT image
         FROM draft_posts
         WHERE draft_id = ? AND user_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $draft_id, $user_id);
    $statement->execute();
    $draft = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($draft === null) {
        $con->close();
        http_response_code(404);
        render_empty_state(
            'Draft unavailable',
            'The draft was removed, no longer exists, or you do not have permission to delete it.',
            'Back to drafts',
            'draft_posts.php',
            'fa-file-alt'
        );
    }

    $image = (string) ($draft['image'] ?? '');

    $statement = $con->prepare(
        'DELETE FROM draft_posts WHERE draft_id = ? AND user_id = ?'
    );
    $statement->bind_param('ii', $draft_id, $user_id);
    $statement->execute();
    $deleted = $statement->affected_rows;
    $statement->close();
    $con->close();

    if ($deleted !== 1) {
        http_response_code(404);
        render_empty_state(
            'Draft unavailable',
            'The draft was removed, no longer exists, or you do not have permission to delete it.',
            'Back to drafts',
            'draft_posts.php',
            'fa-file-alt'
        );
    }

    if ($image !== '') {
        $image_path = dirname(__DIR__) . '/images/' . basename($image);
        if (is_file($image_path)) {
            unlink($image_path);
        }
    }

    header('Location: draft_posts.php');
    exit;
}

$con->close();
http_response_code(400);
render_empty_state(
    'Nothing to delete',
    'Select a valid post or draft before trying again.',
    'Back to Discover',
    'index.php',
    'fa-trash'
);
