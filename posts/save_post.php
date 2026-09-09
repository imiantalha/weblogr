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
        'This action must be submitted from the Weblogr editor.',
        'Back to Discover',
        'index.php',
        'fa-ban'
    );
}

verify_csrf();
require '../database/db.php';

$user_id = (int) $_SESSION['user_id'];
$username = strtoupper((string) $_SESSION['username']);
$is_draft = isset($_POST['draft']) || isset($_POST['save_draft']);
$from_draft = filter_var($_POST['from_draft'] ?? false, FILTER_VALIDATE_BOOLEAN);
$draft_id = filter_var($_POST['draft_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));

$allowed_categories = [
    'education',
    'technology',
    'travel',
    'food',
    'fashion',
    'sport',
    'other',
];

if (
    $title === '' ||
    mb_strlen($title) > 255 ||
    $description === '' ||
    mb_strlen($description) > 10000 ||
    !in_array($category, $allowed_categories, true)
) {
    http_response_code(422);
    $con->close();
    render_empty_state(
        'Check your story',
        'Please provide a title, description and a valid category before saving or publishing.',
        'Back to editor',
        $from_draft ? 'edit_draft.php?draft_id=' . max(1, (int) $draft_id) : 'new_post.php',
        'fa-pen'
    );
}

$filename = null;
$destination = null;
$old_draft_image = null;

if (
    isset($_FILES['uploadimage']) &&
    $_FILES['uploadimage']['error'] !== UPLOAD_ERR_NO_FILE
) {
    if (!is_uploaded_file($_FILES['uploadimage']['tmp_name'])) {
        $con->close();
        http_response_code(400);
        render_empty_state(
            'Image upload failed',
            'We could not process that image. Please choose another file and try again.',
            'Back to editor',
            'new_post.php',
            'fa-image'
        );
    }

    if (
        $_FILES['uploadimage']['error'] !== UPLOAD_ERR_OK ||
        (int) $_FILES['uploadimage']['size'] > 5 * 1024 * 1024
    ) {
        $con->close();
        http_response_code(400);
        render_empty_state(
            'Image upload failed',
            'Please choose a valid image that is 5 MB or smaller.',
            'Back to editor',
            'new_post.php',
            'fa-image'
        );
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['uploadimage']['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        $con->close();
        http_response_code(415);
        render_empty_state(
            'Unsupported image',
            'Only JPG, PNG, GIF and WebP images are supported.',
            'Back to editor',
            'new_post.php',
            'fa-file-image'
        );
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = dirname(__DIR__) . '/images/' . $filename;

    if (!move_uploaded_file($_FILES['uploadimage']['tmp_name'], $destination)) {
        $con->close();
        http_response_code(500);
        render_empty_state(
            'Upload unavailable',
            'We could not save your image right now. Please try again.',
            'Back to editor',
            'new_post.php',
            'fa-cloud-upload-alt'
        );
    }
}

try {
    $con->begin_transaction();

    if ($from_draft) {
        if (!$draft_id) {
            throw new RuntimeException('Invalid draft.');
        }

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
            throw new RuntimeException('Draft not found.');
        }

        $old_draft_image = (string) ($draft['image'] ?? '');

        if ($filename === null) {
            $filename = $draft['image'];
        }
    }

    if ($is_draft) {
        if ($from_draft) {
            $statement = $con->prepare(
                'UPDATE draft_posts
                 SET title = ?, updated_at = CURRENT_TIMESTAMP, image = ?, description = ?, category = ?
                 WHERE draft_id = ? AND user_id = ?'
            );
            $statement->bind_param(
                'ssssii',
                $title,
                $filename,
                $description,
                $category,
                $draft_id,
                $user_id
            );
        } else {
            $statement = $con->prepare(
                'INSERT INTO draft_posts (title, image, description, category, user_id)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'ssssi',
                $title,
                $filename,
                $description,
                $category,
                $user_id
            );
        }

        $statement->execute();
        $statement->close();
        $con->commit();
        $con->close();

        if (
            $from_draft &&
            $filename !== $old_draft_image &&
            $old_draft_image !== ''
        ) {
            $old_path = dirname(__DIR__) . '/images/' . basename($old_draft_image);
            if (is_file($old_path)) {
                unlink($old_path);
            }
        }

        header('Location: draft_posts.php');
        exit;
    }

    $statement = $con->prepare(
        'INSERT INTO blogs (title, image, description, category, user_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $statement->bind_param(
        'ssssi',
        $title,
        $filename,
        $description,
        $category,
        $user_id
    );
    $statement->execute();
    $statement->close();

    $notification_content = "$username posted a new post.";
    $followers = $con->prepare(
        'SELECT follower_id FROM followers WHERE blogger_id = ?'
    );
    $followers->bind_param('i', $user_id);
    $followers->execute();
    $result = $followers->get_result();
    $notification = $con->prepare(
        'INSERT INTO notifications (content, user_id) VALUES (?, ?)'
    );

    while ($follower = $result->fetch_assoc()) {
        $follower_id = (int) $follower['follower_id'];
        $notification->bind_param('si', $notification_content, $follower_id);
        $notification->execute();
    }

    $notification->close();
    $followers->close();

    if ($from_draft) {
        $delete_draft = $con->prepare(
            'DELETE FROM draft_posts WHERE draft_id = ? AND user_id = ?'
        );
        $delete_draft->bind_param('ii', $draft_id, $user_id);
        $delete_draft->execute();
        $delete_draft->close();
    }

    $con->commit();
    $con->close();

    if (
        $from_draft &&
        $filename !== $old_draft_image &&
        $old_draft_image !== ''
    ) {
        $old_path = dirname(__DIR__) . '/images/' . basename($old_draft_image);
        if (is_file($old_path)) {
            unlink($old_path);
        }
    }

    header('Location: index.php');
    exit;
} catch (Throwable $exception) {
    if ($con->errno === 0 || $con->ping()) {
        $con->rollback();
        $con->close();
    }

    if ($filename !== null && $destination !== null && is_file($destination)) {
        unlink($destination);
    }

    error_log('Post save failed: ' . $exception->getMessage());
    http_response_code(500);
    render_empty_state(
        'We could not save your story',
        'Something went wrong while saving your story. Your changes were not completed. Please try again.',
        'Back to editor',
        $from_draft ? 'edit_draft.php?draft_id=' . max(1, (int) $draft_id) : 'new_post.php',
        'fa-exclamation-circle'
    );
}
