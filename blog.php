<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/public_helpers.php';

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$allowed = ['education', 'technology', 'travel', 'food', 'fashion', 'sport', 'other'];

if (!in_array($category, $allowed, true)) {
    $category = '';
}

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = '(b.title LIKE ? OR b.description LIKE ? OR u.username LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
    $types = 'sss';
}

if ($category !== '') {
    $conditions[] = 'b.category = ?';
    $params[] = $category;
    $types .= 's';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $con->prepare(
    'SELECT COUNT(*) total
     FROM blogs b
     LEFT JOIN users u ON u.user_id = b.user_id' . $where
);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$total = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql =
    'SELECT b.blog_id, b.title, b.created_at, b.description, b.category, b.image, b.likes, b.user_id, u.username' .
    $where .
    ' ORDER BY b.created_at DESC LIMIT ? OFFSET ?';

$feed = $params;
$feed[] = $perPage;
$feed[] = $offset;

$stmt = $con->prepare($sql);
$feedTypes = $types . 'ii';
$stmt->bind_param($feedTypes, ...$feed);
$stmt->execute();
$posts = $stmt->get_result();
$query = $_GET;
unset($query['page']);
$stmt->close();
$con->close();

function blog_page_url(int $page, array $query): string
{
    $query['page'] = $page;

    return 'blog.php?' . http_build_query($query);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover public stories, ideas and writers on Weblogr.">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#2563eb">
    <link rel="canonical" href="<?= e(public_url('blog.php')) ?>">
    <link rel="icon" href="assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/weblogr-mark.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="assets/public.css">
    <title><?= $search !== '' ? 'Search: ' . e($search) . ' · ' : '' ?>Discover stories | Weblogr</title>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <nav class="nav" aria-label="Primary">
            <a class="brand" href="index.html" aria-label="Weblogr home">
                <img class="brand-logo" src="assets/weblogr-mark.svg" alt="" aria-hidden="true">
                <span>Weblogr</span>
            </a>

            <button
                class="mobile-menu"
                type="button"
                aria-label="Open navigation"
                aria-expanded="false"
                aria-controls="public-navigation"
            >
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

            <div class="nav-links" id="public-navigation">
                <a href="blog.php" aria-current="page">Discover</a>
                <a href="about.html">About</a>
                <a href="registration/login.php">Log in</a>
                <a class="nav-cta" href="registration/signup.php">Start writing</a>
            </div>
        </nav>
    </header>

    <main id="main">
        <div class="container">
            <section class="page-hero">
                <p class="eyebrow">DISCOVER</p>
                <h1>Stories for curious minds.</h1>
                <p>Read public stories, find new writers and explore ideas from the Weblogr community.</p>
            </section>

            <form class="filters" method="get" action="blog.php" role="search">
                <label class="sr-only" for="story-search">Search stories or writers</label>
                <input
                    id="story-search"
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    maxlength="100"
                    placeholder="Search stories or writers"
                >

                <label class="sr-only" for="story-category">Filter by category</label>
                <select id="story-category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($allowed as $item): ?>
                        <option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>>
                            <?= e(ucfirst($item)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="button button-primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    Search
                </button>

                <?php if ($search !== '' || $category !== ''): ?>
                    <a class="button button-secondary" href="blog.php">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($search !== '' || $category !== ''): ?>
                <p class="result-summary" aria-live="polite">
                    <?= number_format($total) ?> <?= $total === 1 ? 'story' : 'stories' ?> found
                    <?php if ($search !== ''): ?>
                        for <strong><?= e($search) ?></strong>
                    <?php endif; ?>
                    <?php if ($category !== ''): ?>
                        in <strong><?= e(ucfirst($category)) ?></strong>
                    <?php endif; ?>.
                </p>
            <?php endif; ?>

            <?php if ($posts->num_rows): ?>
                <div
                    class="story-grid"
                    data-public-feed
                    data-next-page="<?= $page < $pages ? $page + 1 : 0 ?>"
                    data-per-page="<?= $perPage ?>"
                    data-search="<?= e($search) ?>"
                    data-category="<?= e($category) ?>"
                >
                    <?php while ($post = $posts->fetch_assoc()): ?>
                        <article class="story-card">
                            <a
                                class="story-cover-link"
                                href="<?= e(article_url((int) $post['blog_id'], (string) $post['title'])) ?>"
                                aria-label="Read <?= e((string) $post['title']) ?>"
                            >
                                <?php if (!empty($post['image'])): ?>
                                    <img
                                        class="story-image"
                                        src="images/<?= rawurlencode((string) $post['image']) ?>"
                                        alt=""
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="story-image story-image-placeholder" aria-hidden="true">
                                        <i class="fas fa-pen-nib"></i>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <div class="story-card-body">
                                <span class="category"><?= e(strtoupper((string) $post['category'])) ?></span>
                                <h2>
                                    <a href="<?= e(article_url((int) $post['blog_id'], (string) $post['title'])) ?>">
                                        <?= e((string) $post['title']) ?>
                                    </a>
                                </h2>
                                <p><?= e(excerpt((string) ($post['description'] ?? ''))) ?></p>

                                <div class="story-meta">
                                    <span>
                                        <?php if ($post['user_id']): ?>
                                            <a href="<?= e(author_url((int) $post['user_id'], (string) $post['username'])) ?>">
                                                @<?= e((string) $post['username']) ?>
                                            </a>
                                        <?php else: ?>
                                            Weblogr writer
                                        <?php endif; ?>
                                    </span>
                                    <span><?= e(date('d M Y', strtotime((string) $post['created_at']))) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="pagination" aria-label="Pagination" data-server-pagination>
                        <?php if ($page > 1): ?>
                            <a class="button button-secondary" href="<?= e(blog_page_url($page - 1, $query)) ?>">
                                ← Previous
                            </a>
                        <?php endif; ?>

                        <span class="pagination-current" aria-current="page">
                            Page <?= $page ?> of <?= $pages ?>
                        </span>

                        <?php if ($page < $pages): ?>
                            <a class="button button-primary" href="<?= e(blog_page_url($page + 1, $query)) ?>">
                                Next →
                            </a>
                        <?php endif; ?>
                    </nav>

                    <div data-feed-sentinel class="feed-sentinel" aria-hidden="true"></div>

                    <noscript>
                        <p>JavaScript is disabled. Use the pagination controls above to load more stories.</p>
                    </noscript>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2>No stories found</h2>
                    <p>
                        <?= ($search !== '' || $category !== '')
                            ? 'Try a different search term or category.'
                            : 'The community has not published a story yet. Be the first writer to share an idea.' ?>
                    </p>

                    <?php if ($search !== '' || $category !== ''): ?>
                        <a class="button button-secondary" href="blog.php">Reset filters</a>
                    <?php else: ?>
                        <a class="button button-primary" href="registration/signup.php">Become a writer</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <a class="footer-brand" href="index.html">
                <img src="assets/weblogr-mark.svg" alt="" aria-hidden="true">
                <strong>Weblogr</strong>
            </a>
            <span>Ideas worth sharing.</span>
            <div class="footer-links">
                <a href="blog.php">Discover</a>
                <a href="about.html">About</a>
                <a href="privacy.html">Privacy</a>
                <a href="terms.html">Terms</a>
            </div>
            <span>© 2026 Weblogr</span>
        </div>
    </footer>

    <script>
        const menu = document.querySelector('.mobile-menu');
        const navigation = document.querySelector('.nav-links');

        menu?.addEventListener('click', () => {
            const isOpen = menu.getAttribute('aria-expanded') === 'true';

            menu.setAttribute('aria-expanded', String(!isOpen));
            navigation.classList.toggle('open');
        });
    </script>
    <script src="assets/public-feed.js" defer></script>
</body>
</html>
