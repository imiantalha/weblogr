<?php

declare(strict_types=1);

require '../includes/security.php';
$user_id = require_authentication();
require '../database/db.php';

$error = null;
$profile = [
    'first_name' => '',
    'last_name' => '',
    'username' => '',
    'bio' => '',
    'profile_picture' => '',
];

$statement = $con->prepare(
    'SELECT first_name, last_name, username, bio, profile_picture
     FROM users
     WHERE user_id = ?
     LIMIT 1'
);
$statement->bind_param('i', $user_id);
$statement->execute();
$existing = $statement->get_result()->fetch_assoc();
$statement->close();

if ($existing) {
    $profile = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $first_name = trim((string) ($_POST['first_name'] ?? ''));
    $last_name = trim((string) ($_POST['last_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));

    if (
        $first_name === '' ||
        mb_strlen($first_name) > 50 ||
        $last_name === '' ||
        mb_strlen($last_name) > 50
    ) {
        $error = 'Please enter a valid first and last name.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,255}$/', $username)) {
        $error = 'Username must be 3-255 characters and contain only letters, numbers, dots, underscores, or hyphens.';
    } elseif (mb_strlen($bio) > 1000) {
        $error = 'Bio must be 1,000 characters or fewer.';
    } else {
        $statement = $con->prepare(
            'SELECT user_id
             FROM users
             WHERE username = ? AND user_id <> ?
             LIMIT 1'
        );
        $statement->bind_param('si', $username, $user_id);
        $statement->execute();
        $username_taken = $statement->get_result()->fetch_assoc() !== null;
        $statement->close();

        if ($username_taken) {
            $error = 'That username is already taken. Please choose another one.';
        } else {
            $profile_picture = (string) ($profile['profile_picture'] ?? '');
            $old_profile_picture = $profile_picture;
            $new_file = null;
            $destination = null;

            if (
                isset($_FILES['profile_picture']) &&
                $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $file = $_FILES['profile_picture'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'We could not upload that image. Please choose the file again.';
                } elseif ((int) $file['size'] > 5 * 1024 * 1024) {
                    $error = 'Profile image must be 5 MB or smaller.';
                } elseif (!is_uploaded_file($file['tmp_name'])) {
                    $error = 'The uploaded image could not be verified. Please try again.';
                } else {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                    $extensions = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    ];

                    if (!isset($extensions[$mime])) {
                        $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
                    } else {
                        $upload_dir = dirname(__DIR__) . '/uploads';

                        if (
                            !is_dir($upload_dir) &&
                            !mkdir($upload_dir, 0755, true) &&
                            !is_dir($upload_dir)
                        ) {
                            $error = 'Profile image storage is not available right now.';
                        } else {
                            $new_file = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                            $destination = $upload_dir . '/' . $new_file;

                            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                                $error = 'Unable to save the profile image right now. Please try again.';
                            } else {
                                $profile_picture = $new_file;
                            }
                        }
                    }
                }
            }

            if ($error === null) {
                $statement = $con->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?, username = ?, bio = ?, profile_picture = ?
                     WHERE user_id = ?'
                );
                $statement->bind_param(
                    'sssssi',
                    $first_name,
                    $last_name,
                    $username,
                    $bio,
                    $profile_picture,
                    $user_id
                );

                if (!$statement->execute()) {
                    $error = 'We could not save your profile changes. Please try again.';
                }

                $statement->close();
            }

            if ($error === null) {
                $_SESSION['username'] = $username;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $con->close();

                if (
                    $new_file !== null &&
                    $old_profile_picture !== '' &&
                    $old_profile_picture !== $new_file
                ) {
                    $old_path = dirname(__DIR__) . '/uploads/' . basename($old_profile_picture);
                    if (is_file($old_path)) {
                        unlink($old_path);
                    }
                }

                header('Location: profile.php?saved=1');
                exit;
            }

            if ($new_file !== null && $destination !== null && is_file($destination)) {
                unlink($destination);
            }
        }
    }
}

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <title>Edit Profile | Weblogr</title>
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="../assets/weblogr-mark.svg">
    <script src="../assets/form-validation.js" defer></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../assets/weblogr-product.css">
    <link rel="stylesheet" href="../assets/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<?php include '../posts/sidebar.php'; ?>
<main class="content">
    <div class="profile-form-card">
        <section class="account-card">
            <div class="account-card-header">
                <div class="header-copy">
                    <p class="eyebrow">ACCOUNT</p>
                    <h1>Edit profile</h1>
                    <p>Shape how readers see you across Weblogr.</p>
                </div>
                <a class="secondary-button" href="profile.php">
                    <i class="fas fa-arrow-left"></i> Back to profile
                </a>
            </div>

            <?php if ($error): ?>
                <div class="form-alert" role="alert">
                    <i class="fas fa-circle-exclamation"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="profile-edit-intro">
                <div class="profile-edit-icon"><i class="fas fa-user-pen"></i></div>
                <div>
                    <strong>Your public profile</strong>
                    <span>Your username, name and bio appear alongside your stories and help readers recognize you.</span>
                </div>
            </div>

            <form class="profile-form" action="" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="profile-form-grid">
                    <label>
                        Username
                        <input type="text" name="username" minlength="3" maxlength="255" value="<?php echo e((string) $profile['username']); ?>" required data-label="Username" autocomplete="username">
                        <span class="field-hint">3-255 characters · letters, numbers, dots, underscores, and hyphens.</span>
                    </label>
                    <label>
                        First name
                        <input type="text" name="first_name" maxlength="50" value="<?php echo e((string) $profile['first_name']); ?>" required data-label="First name" autocomplete="given-name">
                    </label>
                    <label>
                        Last name
                        <input type="text" name="last_name" maxlength="50" value="<?php echo e((string) $profile['last_name']); ?>" required data-label="Last name" autocomplete="family-name">
                    </label>
                    <label class="form-field-full">
                        Bio
                        <textarea name="bio" maxlength="1000" placeholder="Tell readers about your interests, writing, or what you like to share..." data-label="Bio"><?php echo e((string) $profile['bio']); ?></textarea>
                        <span class="field-hint"><span>Up to 1,000 characters.</span><span class="bio-counter" data-max="1000">0 / 1,000</span></span>
                    </label>
                    <label class="form-field-full">
                        Profile picture
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp">
                        <span class="field-hint">JPG, PNG, GIF or WebP · maximum 5 MB.</span>
                    </label>
                </div>

                <div class="form-actions">
                    <a class="secondary-button" href="profile.php">Cancel</a>
                    <button class="submit" type="submit"><i class="fas fa-save"></i> Save changes</button>
                </div>
            </form>
        </section>
    </div>
</main>
<style>.profile-edit-intro{display:flex;align-items:center;gap:13px;padding:14px 15px;margin:0 0 22px;border:1px solid #dbe7ff;background:#f6f9ff;border-radius:13px;color:#334155}.profile-edit-icon{width:40px;height:40px;flex:0 0 40px;border-radius:11px;display:grid;place-items:center;background:#e7efff;color:#2563eb}.profile-edit-intro strong{display:block;font-size:13px;margin-bottom:2px}.profile-edit-intro span{display:block;color:#64748b;font-size:12px;line-height:1.5}.profile-form .field-hint{display:flex;justify-content:space-between;gap:12px}.bio-counter{white-space:nowrap}.profile-form label input[type=file]{cursor:pointer;background:#f8fafc}.profile-form label input[type=file]::file-selector-button{border:1px solid #dbe2ea;background:#fff;border-radius:7px;padding:7px 10px;margin-right:10px;font:inherit;font-weight:600;color:#334155;cursor:pointer}@media(max-width:600px){.account-card-header{flex-direction:column}.account-card-header .secondary-button{width:100%}.profile-edit-intro{align-items:flex-start}.profile-form .field-hint{align-items:flex-start;flex-direction:column;gap:2px}}</style>
<script>document.addEventListener('DOMContentLoaded',function(){const bio=document.querySelector('textarea[name="bio"]'),counter=document.querySelector('.bio-counter');if(bio&&counter){const update=()=>counter.textContent=bio.value.length.toLocaleString()+' / 1,000';bio.addEventListener('input',update);update();}});</script>
</body>
</html>
