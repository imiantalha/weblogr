<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();
require '../database/db.php';

$user_id = (int) $_SESSION['user_id'];
$statement = $con->prepare('SELECT user_type FROM users WHERE user_id=? LIMIT 1');
$statement->bind_param('i', $user_id);
$statement->execute();
$user = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$user || $user['user_type'] !== 'Admin') {
    $con->close();
    http_response_code(403);
    exit('Administrator access required.');
}

$stats = [];
foreach ([
    'users' => 'SELECT COUNT(*) total FROM users',
    'posts' => 'SELECT COUNT(*) total FROM blogs',
    'drafts' => 'SELECT COUNT(*) total FROM draft_posts',
    'comments' => 'SELECT COUNT(*) total FROM comments',
    'reports' => 'SELECT COUNT(*) total FROM reports',
] as $key => $sql) {
    $r = $con->query($sql);
    $stats[$key] = (int) $r->fetch_assoc()['total'];
}

$recent = $con->query('SELECT b.blog_id,b.title,b.created_at,u.username FROM blogs b JOIN users u ON u.user_id=b.user_id ORDER BY b.created_at DESC LIMIT 8');

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
    <title>Admin Dashboard | Weblogr</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content">
        <div class="all-posts-container">
            <header class="feed-header">
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>Weblogr control center</h1>
                <p>Monitor platform activity and moderate community content.</p>
            </header>

            <section class="admin-grid">
                <div class="admin-stat"><i class="fas fa-users"></i><strong><?php echo $stats['users']; ?></strong><span>Users</span></div>
                <div class="admin-stat"><i class="fas fa-file-alt"></i><strong><?php echo $stats['posts']; ?></strong><span>Published posts</span></div>
                <div class="admin-stat"><i class="fas fa-edit"></i><strong><?php echo $stats['drafts']; ?></strong><span>Drafts</span></div>
                <div class="admin-stat"><i class="fas fa-comments"></i><strong><?php echo $stats['comments']; ?></strong><span>Comments</span></div>
                <div class="admin-stat"><i class="fas fa-flag"></i><strong><?php echo $stats['reports']; ?></strong><span>Reports</span></div>
            </section>

            <section class="admin-actions">
                <a class="submit" href="manage_content.php"><i class="fas fa-shield-alt"></i> Moderate content</a>
                <a class="secondary-button" href="index.php"><i class="fas fa-newspaper"></i> View feed</a>
            </section>

            <section>
                <div class="profile-section-heading">
                    <div>
                        <p class="eyebrow">CONTENT</p>
                        <h2>Recent posts</h2>
                    </div>
                </div>

                <?php if ($recent->num_rows): ?>
                    <?php while ($row = $recent->fetch_assoc()): ?>
                        <article class="admin-row">
                            <div>
                                <strong><?php echo e((string) $row['title']); ?></strong>
                                <span>@<?php echo e((string) $row['username']); ?> · <?php echo e(date('d M Y', strtotime((string) $row['created_at']))); ?></span>
                            </div>
                            <a href="../comments/comments.php?blog_id=<?php echo (int) $row['blog_id']; ?>">Open <i class="fas fa-arrow-right"></i></a>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h2>No posts yet</h2>
                        <p>Content activity will appear here.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
<?php
$recent->free();
$con->close();
