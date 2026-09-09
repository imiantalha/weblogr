<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();
require '../database/db.php';
require '../includes/view_helpers.php';

$user_id = (int) $_SESSION['user_id'];
$s = $con->prepare('SELECT user_type FROM users WHERE user_id=? LIMIT 1');
$s->bind_param('i', $user_id);
$s->execute();
$admin = $s->get_result()->fetch_assoc();
$s->close();

if (!$admin || $admin['user_type'] !== 'Admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$users = $con->query('SELECT u.user_id,u.username,u.user_type,(SELECT COUNT(*) FROM blogs b WHERE b.user_id=u.user_id) posts,(SELECT COUNT(*) FROM followers f WHERE f.blogger_id=u.user_id) followers FROM users u ORDER BY u.user_id DESC');

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Users | Weblogr Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content">
        <div class="all-posts-container">
            <header class="feed-header">
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>User management</h1>
                <p>Review community accounts and their activity.</p>
            </header>

            <section class="admin-table">
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while ($row = $users->fetch_assoc()): ?>
                        <article class="admin-row">
                            <div>
                                <strong><?php echo e(strtoupper((string) $row['username'])); ?></strong>
                                <span><?php echo e((string) $row['user_type']); ?> · <?php echo (int) $row['posts']; ?> posts · <?php echo (int) $row['followers']; ?> followers</span>
                            </div>
                            <a href="blog_poster.php?user_id=<?php echo (int) $row['user_id']; ?>">Profile <i class="fas fa-arrow-right"></i></a>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-users"></i></div>
                        <h2>No users yet</h2>
                        <p>Community accounts will appear here once users register.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
<?php
if ($users) {
    $users->free();
}
$con->close();
