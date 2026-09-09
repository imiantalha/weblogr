<?php

declare(strict_types=1);

require '../includes/security.php';
require '../includes/public_helpers.php';
$user_id = require_authentication();
require '../database/db.php';

$blog_id = filter_var($_GET['blog_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$blog_id) {
    http_response_code(400);
    $page_error = 'We could not identify that post.';
}

$blog = null;
if ($blog_id) {
    $statement = $con->prepare(
        'SELECT blog_id, title, user_id
         FROM blogs
         WHERE blog_id = ?
         LIMIT 1'
    );
    $statement->bind_param('i', $blog_id);
    $statement->execute();
    $blog = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$blog) {
        http_response_code(404);
        $page_error = 'That post could not be found.';
    }
}

$is_own_post = $blog && (int) $blog['user_id'] === $user_id;
$error = '';
$success = false;

if ($blog && !$is_own_post && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

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

    if (!in_array($reason, $allowed, true)) {
        $error = 'Please choose a reason for your report.';
    } elseif (mb_strlen($details) > 2000) {
        $error = 'Additional details must be 2000 characters or fewer.';
    } else {
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
            $error = 'You already have a pending report for this post.';
        } else {
            $statement = $con->prepare(
                'INSERT INTO reports (blog_id, reporter_id, reason, details)
                 VALUES (?, ?, ?, ?)'
            );
            $statement->bind_param('iiss', $blog_id, $user_id, $reason, $details);
            $statement->execute();
            $statement->close();
            $success = true;
        }
    }
}

$csrf = e(csrf_token());
$title = $blog ? e((string) $blog['title']) : '';
$story_url = $blog
    ? '../article.php?id=' . (int) $blog_id
    : '../index.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_own_post ? 'Report unavailable' : 'Report Post'; ?> | Weblogr</title>
    <link rel="icon" href="../assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="../assets/weblogr-mark.svg">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../assets/responsive.css">
    <link rel="stylesheet" href="../assets/weblogr-product.css">
    <style>
        .report-page{max-width:760px;margin:0 auto;padding:40px 24px 70px}.report-shell{background:#fff;border:1px solid #e7ebf0;border-radius:22px;box-shadow:0 12px 40px rgba(15,23,42,.07);overflow:hidden}.report-header{padding:34px 36px 24px;border-bottom:1px solid #edf0f4}.report-eyebrow{margin:0 0 10px;color:#2563eb;font-size:12px;font-weight:800;letter-spacing:.12em}.report-header h1{margin:0;color:#172033;font-size:30px;line-height:1.2}.report-header p{margin:10px 0 0;color:#687386;line-height:1.65}.report-body{padding:30px 36px}.report-post{display:flex;align-items:center;gap:14px;padding:16px;background:#f7f9fc;border:1px solid #e9edf3;border-radius:14px;margin-bottom:24px}.report-post-icon{width:42px;height:42px;flex:0 0 42px;border-radius:12px;display:grid;place-items:center;background:#eaf1ff;color:#2563eb}.report-post strong{display:block;color:#172033;font-size:15px}.report-post span{display:block;color:#8791a2;font-size:12px;margin-top:4px}.report-field{display:block;margin-bottom:20px}.report-field span{display:block;color:#293347;font-size:14px;font-weight:700;margin-bottom:8px}.report-field select,.report-field textarea{width:100%;box-sizing:border-box;border:1px solid #dfe4eb;border-radius:12px;background:#fff;padding:13px 14px;font:inherit;color:#172033;outline:none}.report-field select:focus,.report-field textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.10)}.report-field textarea{resize:vertical;min-height:140px}.report-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px}.report-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer;border:1px solid #dfe4eb;background:#fff;color:#293347}.report-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.report-btn.primary:hover{background:#1d4ed8}.report-alert{padding:13px 15px;border-radius:11px;margin-bottom:20px;font-size:14px}.report-alert.error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.report-state{text-align:center;padding:44px 30px}.report-state-icon{width:62px;height:62px;margin:0 auto 18px;border-radius:18px;display:grid;place-items:center;background:#eef4ff;color:#2563eb;font-size:25px}.report-state h2{margin:0;color:#172033;font-size:23px}.report-state p{max-width:500px;margin:10px auto 24px;color:#687386;line-height:1.65}.report-state-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}@media(max-width:700px){.report-page{padding:24px 15px 50px}.report-header,.report-body{padding:25px 20px}.report-header h1{font-size:25px}.report-actions{flex-direction:column-reverse}.report-btn{width:100%}}
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="content">
<section class="report-page">
<div class="report-shell">
<?php if (!$blog): ?>
    <div class="report-state">
        <div class="report-state-icon"><i class="fas fa-file-circle-xmark"></i></div>
        <h2>Post unavailable</h2>
        <p><?php echo e($page_error ?? 'We could not find the post you were looking for. It may have been removed or the link may be outdated.'); ?></p>
        <div class="report-state-actions"><a class="report-btn primary" href="index.php">Back to Discover</a></div>
    </div>
<?php elseif ($success): ?>
    <div class="report-state">
        <div class="report-state-icon"><i class="fas fa-circle-check"></i></div>
        <h2>Report submitted</h2>
        <p>Thanks for helping keep Weblogr welcoming. Our moderation team will review your report and take appropriate action if needed.</p>
        <div class="report-state-actions"><a class="report-btn primary" href="<?php echo e($story_url); ?>">Back to post</a><a class="report-btn" href="index.php">Continue reading</a></div>
    </div>
<?php elseif ($is_own_post): ?>
    <div class="report-state">
        <div class="report-state-icon"><i class="fas fa-shield-heart"></i></div>
        <h2>You can't report your own post</h2>
        <p>Reports are intended for content published by other users. If you want to make a change to this story, you can edit it from your posts.</p>
        <div class="report-state-actions"><a class="report-btn primary" href="<?php echo e($story_url); ?>">Back to post</a><a class="report-btn" href="user_posts.php">My Posts</a></div>
    </div>
<?php else: ?>
    <header class="report-header"><p class="report-eyebrow">COMMUNITY SAFETY</p><h1>Report this post</h1><p>Tell our moderation team what needs attention. Reports are reviewed carefully and kept private.</p></header>
    <div class="report-body">
        <div class="report-post"><div class="report-post-icon"><i class="fas fa-flag"></i></div><div><strong><?php echo $title; ?></strong><span>Post #<?php echo (int) $blog_id; ?></span></div></div>
        <?php if ($error): ?><div class="report-alert error" role="alert"><i class="fas fa-circle-exclamation"></i> <?php echo e($error); ?></div><?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <label class="report-field"><span>Why are you reporting this post?</span><select name="reason" required><option value="">Choose a reason</option><option value="spam">Spam or misleading content</option><option value="harassment">Harassment or bullying</option><option value="hate or abuse">Hate or abusive content</option><option value="misinformation">Misinformation</option><option value="copyright">Copyright concern</option><option value="other">Something else</option></select></label>
            <label class="report-field"><span>Additional details <small>(optional)</small></span><textarea name="details" maxlength="2000" placeholder="Tell us what happened and add any context that may help our moderation team..."></textarea></label>
            <div class="report-actions"><a class="report-btn" href="<?php echo e($story_url); ?>">Cancel</a><button class="report-btn primary" type="submit"><i class="fas fa-flag"></i>&nbsp; Submit report</button></div>
        </form>
    </div>
<?php endif; ?>
</div>
</section>
</main>
</body>
</html>
<?php if (isset($con) && $con instanceof mysqli) { $con->close(); } ?>
