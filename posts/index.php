<?php

declare(strict_types=1);

require '../includes/security.php';
require_authentication();
require '../database/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$csrf = e(csrf_token());
$viewer_id = (int) $_SESSION['user_id'];

$category = trim((string) ($_GET['category'] ?? ''));
$username_filter = trim((string) ($_GET['username'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest_first');
$popularity = (string) ($_GET['popularity'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 8;

$allowed_categories = [
    'education',
    'technology',
    'travel',
    'food',
    'fashion',
    'sport',
    'other',
];
$allowed_sorts = ['newest_first', 'oldest_first'];
$allowed_popularity = ['popular', 'unpopular'];

if (!in_array($category, $allowed_categories, true)) {
    $category = '';
}

if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'newest_first';
}

if (!in_array($popularity, $allowed_popularity, true)) {
    $popularity = '';
}

$users = $con->query('SELECT username FROM users ORDER BY username ASC');

$base = ' FROM blogs b JOIN users u ON b.user_id=u.user_id';
$conditions = [];
$params = [];
types = '';

if ($category !== '') {
    $conditions[] = 'b.category=?';
    $params[] = $category;
    $types .= 's';
}

if ($username_filter !== '') {
    $conditions[] = 'u.username=?';
    $params[] = $username_filter;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = '(b.title LIKE ? OR b.description LIKE ? OR u.username LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'sss';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$count_statement = $con->prepare('SELECT COUNT(*) AS total' . $base . $where);

if ($params) {
    $count_statement->bind_param($types, ...$params);
}

$count_statement->execute();
$total = (int) $count_statement->get_result()->fetch_assoc()['total'];
$count_statement->close();

$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$order = $popularity === 'popular'
    ? 'b.likes DESC,b.created_at DESC'
    : ($popularity === 'unpopular'
        ? 'b.likes ASC,b.created_at DESC'
        : ($sort === 'oldest_first' ? 'b.created_at ASC' : 'b.created_at DESC'));

$sql = 'SELECT b.blog_id,b.title,b.created_at,b.image,b.description,b.likes,b.user_id,u.username,b.category,CASE WHEN pl.blog_id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer'
    . $base
    . ' LEFT JOIN post_likes pl ON pl.blog_id=b.blog_id AND pl.user_id=?'
    . $where
    . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';

$feed_params = [$viewer_id, ...$params, $per_page, $offset];
$feed_types = 'i' . $types . 'ii';
$statement = $con->prepare($sql);
$statement->bind_param($feed_types, ...$feed_params);
$statement->execute();
$result = $statement->get_result();

$query = $_GET;
unset($query['page']);

function page_url(int $page, array $query): string
{
    $query['page'] = $page;
    return 'index.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover stories, perspectives and ideas from the Weblogr community.">
    <title>Discover | Weblogr</title>
    <script src="index.js" defer></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <script src="../scripts/script.js" defer></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="content">
        <div class="all-posts-container">
            <header class="feed-header">
                <p class="eyebrow">YOUR READING SPACE</p>
                <h1>Find your next read.</h1>
                <p>Explore ideas, follow writers and discover something worth sharing.</p>
            </header>

            <form action="index.php" method="get" class="post-filters">
                <div class="search-field">
                    <i class="fas fa-search"></i>
                    <input
                        name="search"
                        value="<?php echo e($search); ?>"
                        maxlength="100"
                        placeholder="Search stories, authors or topics..."
                        aria-label="Search posts"
                    >
                </div>

                <select name="category" class="filter">
                    <option value="">All categories</option>
                    <?php foreach ($allowed_categories as $option): ?>
                        <option value="<?php echo e($option); ?>" <?php echo $category === $option ? 'selected' : ''; ?>>
                            <?php echo e(ucfirst($option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="username" class="filter">
                    <option value="">All authors</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo e($user['username']); ?>" <?php echo $username_filter === $user['username'] ? 'selected' : ''; ?>>
                            <?php echo e($user['username']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="sort" class="filter">
                    <option value="newest_first" <?php echo $sort === 'newest_first' ? 'selected' : ''; ?>>Newest</option>
                    <option value="oldest_first" <?php echo $sort === 'oldest_first' ? 'selected' : ''; ?>>Oldest</option>
                </select>

                <select name="popularity" class="filter">
                    <option value="">Popularity</option>
                    <option value="popular" <?php echo $popularity === 'popular' ? 'selected' : ''; ?>>Most popular</option>
                    <option value="unpopular" <?php echo $popularity === 'unpopular' ? 'selected' : ''; ?>>Least popular</option>
                </select>

                <button type="submit" class="submit">
                    <i class="fas fa-search"></i>
                    Search
                </button>

                <?php if ($search || $category || $username_filter || $popularity || $sort !== 'newest_first'): ?>
                    <a href="index.php" class="clear-filter">Clear</a>
                <?php endif; ?>
            </form>

            <p class="results-count">
                <?php echo $total; ?>
                <?php echo $total === 1 ? 'story' : 'stories'; ?> found
            </p>

            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $is_liked = (int) $row['liked_by_viewer'] === 1; ?>
                    <article class="post-container">
                        <span id="display-title"><?php echo e((string) $row['title']); ?></span>

                        <div class="post-meta">
                            <span>
                                <a href="blog_poster.php?user_id=<?php echo (int) $row['user_id']; ?>">
                                    @<?php echo e((string) $row['username']); ?>
                                </a>
                            </span>
                            <span>
                                <i class="far fa-calendar"></i>
                                <?php echo e(date('d M Y', strtotime((string) $row['created_at']))); ?>
                            </span>

                            <?php if (!empty($row['category'])): ?>
                                <span>
                                    <i class="far fa-folder"></i>
                                    <?php echo e(ucfirst((string) $row['category'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($row['image'])): ?>
                            <img
                                id="display-image"
                                src="../images/<?php echo rawurlencode((string) $row['image']); ?>"
                                alt="<?php echo e((string) $row['title']); ?>"
                                loading="lazy"
                            >
                        <?php endif; ?>

                        <p id="display-para"><?php echo nl2br(e((string) $row['description'])); ?></p>

                        <div class="post-actions">
                            <button
                                type="button"
                                class="icon-button like-control<?php echo $is_liked ? ' liked' : ''; ?>"
                                onclick="likeBlog(<?php echo (int) $row['blog_id']; ?>,'<?php echo $csrf; ?>',this)"
                                aria-label="<?php echo $is_liked ? 'Unlike post' : 'Like post'; ?>"
                                aria-pressed="<?php echo $is_liked ? 'true' : 'false'; ?>"
                            >
                                <i class="fas fa-heart"></i>
                                <span id="like-count-<?php echo (int) $row['blog_id']; ?>">
                                    <?php echo (int) $row['likes']; ?>
                                </span>
                            </button>

                            <a href="../comments/comments.php?blog_id=<?php echo (int) $row['blog_id']; ?>">
                                <i class="far fa-comment"></i>
                                Discuss
                            </a>

                            <a href="report.php?blog_id=<?php echo (int) $row['blog_id']; ?>&blogger_id=<?php echo (int) $row['user_id']; ?>">
                                <i class="far fa-flag"></i>
                                Report
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <h2>No stories here yet</h2>
                    <p>
                        <?php echo $search || $category || $username_filter
                            ? 'We could not find a story matching those filters.'
                            : 'The community has not published a story yet. Be the first to start the conversation.'; ?>
                    </p>

                    <?php if ($search || $category || $username_filter): ?>
                        <a href="index.php" class="submit">Reset discovery</a>
                    <?php else: ?>
                        <a href="new_post.php" class="submit">
                            <i class="fas fa-pen"></i>
                            Write the first story
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

                    <div>
                        <?php if ($page > 1): ?>
                            <a href="<?php echo e(page_url($page - 1, $query)); ?>" class="secondary-button">
                                ← Previous
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo e(page_url($page + 1, $query)); ?>" class="submit">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <?php
    $statement->close();
    $con->close();
    ?>
</body>
</html>
