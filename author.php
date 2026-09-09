<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/public_helpers.php';

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(404);
    exit('Not found');
}

$stmt = $con->prepare(
    'SELECT u.user_id, u.first_name, u.last_name, u.username, u.user_type, u.bio, u.profile_picture,
            COUNT(DISTINCT b.blog_id) post_count,
            COALESCE(SUM(b.likes), 0) likes
     FROM users u
     LEFT JOIN blogs b ON b.user_id = u.user_id
     WHERE u.user_id = ?
     GROUP BY u.user_id, u.first_name, u.last_name, u.username, u.user_type, u.bio, u.profile_picture
     LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$author = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$author) {
    http_response_code(404);
    exit('Not found');
}

$author['full_name'] = trim((string) $author['first_name'] . ' ' . (string) $author['last_name']);
$canonical = author_url((int) $author['user_id'], (string) $author['username']);
$requested = trim((string) ($_GET['slug'] ?? ''));

if ($requested !== '' && $requested !== slugify((string) $author['username'])) {
    header('Location: ' . $canonical, true, 301);
    exit;
}

$stmt = $con->prepare(
    'SELECT blog_id, title, created_at, description, category, image, likes
     FROM blogs
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$posts = $stmt->get_result();

$description = excerpt((string) ($author['bio'] ?? ''), 155);

if ($description === '') {
    $description = 'Stories and perspectives published by ' . (string) $author['username'] . ' on Weblogr.';
}

$con->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="Weblogr">
    <meta property="og:title" content="<?= e((string) $author['username']) ?> | Weblogr">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e((string) $author['username']) ?> | Weblogr">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <link rel="icon" href="assets/weblogr-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="assets/public.css">
    <title><?= e((string) $author['username']) ?> | Weblogr</title>
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'mainEntity' => [
                '@type' => 'Person',
                'name' => (string) $author['full_name'],
                'alternateName' => (string) $author['username'],
                'url' => $canonical,
                'description' => $description,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
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
                <a href="blog.php">Discover</a>
                <a href="about.html">About</a>
                <a href="registration/login.php">Log in</a>
                <a class="nav-cta" href="registration/signup.php">Start writing</a>
            </div>
        </nav>
    </header>

    <main id="main">
        <div class="container">
            <section class="author-hero">
                <div class="author-avatar" aria-hidden="true">
                    <?= e(strtoupper(substr((string) $author['username'], 0, 1))) ?>
                </div>
                <div>
                    <p class="eyebrow">WRITER PROFILE</p>
                    <h1><?= e((string) $author['full_name']) ?></h1>
                    <p>@<?= e((string) $author['username']) ?></p>
                    <p><?= e((string) ($author['bio'] ?? 'Sharing ideas with the Weblogr community.')) ?></p>

                    <div class="author-stats">
                        <div>
                            <strong><?= number_format((int) $author['post_count']) ?></strong>
                            <span>Stories</span>
                        </div>
                        <div>
                            <strong><?= number_format((int) $author['likes']) ?></strong>
                            <span>Likes</span>
                        </div>
                    </div>
                </div>
            </section>

            <h2 class="section-heading">Latest stories</h2>

            <?php if ($posts->num_rows): ?>
                <div class="story-grid">
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
                                    <span><?= e(date('d M Y', strtotime((string) $post['created_at']))) ?></span>
                                    <span><?= number_format((int) $post['likes']) ?> likes</span>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2>No stories yet</h2>
                    <p>This writer has not published a story yet.</p>
                    <a class="button button-primary" href="blog.php">Explore other stories</a>
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
</body>
</html>
