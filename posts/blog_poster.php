<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();
require '../database/db.php';
require '../includes/view_helpers.php';

$user_id = filter_var($_GET['user_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$user_id) {
    $con->close();
    render_not_found('Profile unavailable', 'The profile link is missing or invalid.');
}

$viewer_id = (int) $_SESSION['user_id'];
$csrf = e(csrf_token());

$statement = $con->prepare(
    'SELECT user_id, username, user_type
     FROM users
     WHERE user_id = ?
     LIMIT 1'
);
$statement->bind_param('i', $user_id);
$statement->execute();
$poster = $statement->get_result()->fetch_assoc();
$statement->close();

if ($poster === null) {
    $con->close();
    render_not_found('Profile not found', 'This blogger account may have been removed.');
}

$statement = $con->prepare('SELECT COUNT(*) total FROM followers WHERE blogger_id = ?');
$statement->bind_param('i', $user_id);
$statement->execute();
$followers = (int) $statement->get_result()->fetch_assoc()['total'];
$statement->close();

$statement = $con->prepare('SELECT COUNT(*) total FROM followers WHERE follower_id = ?');
$statement->bind_param('i', $user_id);
$statement->execute();
$following = (int) $statement->get_result()->fetch_assoc()['total'];
$statement->close();

$statement = $con->prepare(
    'SELECT 1
     FROM followers
     WHERE blogger_id = ? AND follower_id = ?
     LIMIT 1'
);
$statement->bind_param('ii', $user_id, $viewer_id);
$statement->execute();
$is_following = $statement->get_result()->num_rows === 1;
$statement->close();

$statement = $con->prepare('SELECT COUNT(*) total FROM blogs WHERE user_id = ?');
$statement->bind_param('i', $user_id);
$statement->execute();
$post_count = (int) $statement->get_result()->fetch_assoc()['total'];
$statement->close();

$statement = $con->prepare('SELECT COALESCE(SUM(likes), 0) total FROM blogs WHERE user_id = ?');
$statement->bind_param('i', $user_id);
$statement->execute();
$likes = (int) $statement->get_result()->fetch_assoc()['total'];
$statement->close();

$statement = $con->prepare(
    'SELECT blog_id, title, created_at, image, description, likes
     FROM blogs
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$statement->bind_param('i', $user_id);
$statement->execute();
$result = $statement->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(strtoupper((string) $poster['username'])) ?> | Weblogr</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content">
        <div class="all-posts-container">
            <section class="profile-header profile-card">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr((string) $poster['username'], 0, 1))) ?>
                </div>

                <div class="profile-main">
                    <p class="eyebrow">BLOGGER PROFILE</p>
                    <h1><?= e(strtoupper((string) $poster['username'])) ?></h1>
                    <p>
                        <?= e((string) $poster['user_type']) ?> · Sharing ideas with the Weblogr community.
                    </p>

                    <div class="profile-stats">
                        <div>
                            <strong><?= $post_count ?></strong>
                            <span>Posts</span>
                        </div>
                        <div>
                            <strong><?= $followers ?></strong>
                            <span>Followers</span>
                        </div>
                        <div>
                            <strong><?= $following ?></strong>
                            <span>Following</span>
                        </div>
                        <div>
                            <strong><?= $likes ?></strong>
                            <span>Likes</span>
                        </div>
                    </div>
                </div>

                <?php if ($user_id !== $viewer_id): ?>
                    <div>
                        <?php if ($is_following): ?>
                            <form action="unfollow.php" method="post">
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button class="secondary-button" type="submit">
                                    <i class="fas fa-check"></i>
                                    Following
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="follow.php" method="post">
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button class="submit" type="submit">
                                    <i class="fas fa-user-plus"></i>
                                    Follow
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="profile-section-heading">
                <div>
                    <p class="eyebrow">LATEST WRITING</p>
                    <h2>Posts by <?= e((string) $poster['username']) ?></h2>
                </div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <article class="post-container">
                        <span id="display-title"><?= e((string) $row['title']) ?></span>

                        <div class="post-meta">
                            <span><?= e(date('d M Y', strtotime((string) $row['created_at']))) ?></span>
                            <span><i class="fas fa-heart"></i> <?= (int) $row['likes'] ?></span>
                        </div>

                        <?php if (!empty($row['image'])): ?>
                            <img
                                id="display-image"
                                src="../images/<?= rawurlencode((string) $row['image']) ?>"
                                alt="<?= e((string) $row['title']) ?>"
                                loading="lazy"
                            >
                        <?php endif; ?>

                        <p id="display-para"><?= nl2br(e((string) $row['description'])) ?></p>

                        <div class="post-actions">
                            <a href="../comments/comments.php?blog_id=<?= (int) $row['blog_id'] ?>">
                                <i class="far fa-comment"></i>
                                Discuss
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="far fa-file-alt"></i>
                    </div>
                    <h2>No posts yet</h2>
                    <p>This blogger hasn't published anything yet. Check back later for new stories.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php
$statement->close();
$con->close();
?>
