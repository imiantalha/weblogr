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
    !$blog_id ||
    $title === '' ||
    mb_strlen($title) > 255 ||
    $description === '' ||
    mb_strlen($description) > 10000 ||
    !in_array($category, $allowed_categories, true)
) {
    $con->close();
    http_response_code(422);
    exit('Invalid post data.');
}

$filename = null;
$destination = null;
$old_image = null;

if (
    isset($_FILES['uploadimage']) &&
    $_FILES['uploadimage']['error'] !== UPLOAD_ERR_NO_FILE
) {
    if (!is_uploaded_file($_FILES['uploadimage']['tmp_name'])) {
        $con->close();
        http_response_code(400);
        exit('Invalid image upload.');
    }

    if (
        $_FILES['uploadimage']['error'] !== UPLOAD_ERR_OK ||
        (int) $_FILES['uploadimage']['size'] > 5 * 1024 * 1024
    ) {
        $con->close();
        http_response_code(400);
        exit('Image upload failed or exceeds 5 MB.');
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
        exit('Only JPG, PNG, GIF, and WebP images are allowed.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = dirname(__DIR__) . '/images/' . $filename;

    if (!move_uploaded_file($_FILES['uploadimage']['tmp_name'], $destination)) {
        $con->close();
        http_response_code(500);
        exit('Unable to save the uploaded image.');
    }
}

try {
    $statement = $con->prepare(
        'SELECT image
         FROM blogs
         WHERE blog_id = ? AND user_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $blog_id, $user_id);
    $statement->execute();
    $post = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($post === null) {
        throw new RuntimeException('Post not found or access denied.');
    }

    $old_image = (string) ($post['image'] ?? '');

    if ($filename !== null) {
        $statement = $con->prepare(
            'UPDATE blogs
             SET title = ?, image = ?, description = ?, category = ?
             WHERE blog_id = ? AND user_id = ?'
        );
        $statement->bind_param(
            'ssssii',
            $title,
            $filename,
            $description,
            $category,
            $blog_id,
            $user_id
        );
    } else {
        $statement = $con->prepare(
            'UPDATE blogs
             SET title = ?, description = ?, category = ?
             WHERE blog_id = ? AND user_id = ?'
        );
        $statement->bind_param(
            'sssii',
            $title,
            $description,
            $category,
            $blog_id,
            $user_id
        );
    }

    $statement->execute();
    $statement->close();
    $con->close();

    if ($filename !== null && $old_image !== '' && $old_image !== $filename) {
        $old_path = dirname(__DIR__) . '/images/' . basename($old_image);
        if (is_file($old_path)) {
            unlink($old_path);
        }
    }

    header('Location: index.php');
    exit;
} catch (Throwable $exception) {
    if ($destination !== null && is_file($destination)) {
        unlink($destination);
    }

    error_log('Post update failed: ' . $exception->getMessage());
    $con->close();
    http_response_code(
        $exception->getMessage() === 'Post not found or access denied.' ? 404 : 500
    );
    echo $exception->getMessage() === 'Post not found or access denied.'
        ? 'Post not found or access denied.'
        : 'Unable to update the post.';
}
