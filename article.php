<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/public_helpers.php';
require_once __DIR__ . '/includes/security.php';

start_secure_session();

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    public_not_found('Story not found', 'The story link is invalid or incomplete.');
}

$stmt = $con->prepare(
    'SELECT b.blog_id, b.title, b.created_at, b.updated_at, b.description, b.category, b.image, b.likes, b.user_id,
            u.username, u.user_type,
            (SELECT COUNT(*) FROM comments c WHERE c.blog_id = b.blog_id) comment_count
     FROM blogs b
     LEFT JOIN users u ON u.user_id = b.user_id
     WHERE b.blog_id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    public_not_found();
}

$canonical = article_url((int) $post['blog_id'], (string) $post['title']);
$requested = trim((string) ($_GET['slug'] ?? ''));
$expected = slugify((string) $post['title']);

if ($requested !== '' && $requested !== $expected) {
    header('Location: ' . $canonical, true, 301);
    exit;
}

$title = (string) $post['title'];
$description = excerpt((string) ($post['description'] ?? ''));
$author = (string) ($post['username'] ?? 'Weblogr writer');
$authorLink = $post['user_id']
    ? author_url((int) $post['user_id'], $author)
    : public_url('blog.php');
$image = !empty($post['image'])
    ? public_url('images/' . rawurlencode((string) $post['image']))
    : public_url('assets/weblogr-mark.svg');
$related = [];

$stmt = $con->prepare(
    'SELECT b.blog_id, b.title, b.created_at, b.category, b.user_id, u.username
     FROM blogs b
     LEFT JOIN users u ON u.user_id = b.user_id
     WHERE b.category = ? AND b.blog_id <> ?
     ORDER BY b.created_at DESC
     LIMIT 4'
);
$category = (string) $post['category'];
$stmt->bind_param('si', $category, $id);
$stmt->execute();
$r = $stmt->get_result();

while ($row = $r->fetch_assoc()) {
    $related[] = $row;
}

$stmt->close();

$comments = (int) $post['comment_count'];
$con->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($description) ?>">
    <meta name="author" content="<?= e($author) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Weblogr">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($image) ?>">
    <meta property="og:image:alt" content="<?= e($title) ?>">
    <meta property="article:published_time" content="<?= e((string) $post['created_at']) ?>">
    <meta property="article:modified_time" content="<?= e((string) $post['updated_at']) ?>">
    <meta property="article:section" content="<?= e((string) $post['category']) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($image) ?>">
    <link rel="icon" href="<?= e(public_url('assets/weblogr-mark.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(public_url('assets/weblogr-mark.svg')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(public_url('assets/public.css')) ?>">
    <title><?= e($title) ?> | Weblogr</title>
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'datePublished' => (string) $post['created_at'],
            'dateModified' => (string) $post['updated_at'],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'image' => [$image],
            'articleSection' => (string) $post['category'],
            'author' => [
                '@type' => 'Person',
                'name' => $author,
                'url' => $authorLink,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Weblogr',
                'url' => app_base_url(),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <?php include __DIR__ . '/includes/public_header.php'; ?>

    <main id="main">
        <div class="container article-layout">
            <article class="article">
                <header class="article-header">
                    <p class="eyebrow"><?= e(strtoupper((string) $post['category'])) ?></p>
                    <h1><?= e($title) ?></h1>
                    <p class="article-description"><?= e($description) ?></p>

                    <div class="article-byline">
                        <span class="avatar" aria-hidden="true">
                            <?= e(strtoupper(substr($author, 0, 1))) ?>
                        </span>
                        <div>
                            <a href="<?= e($authorLink) ?>">
                                <strong><?= e($author) ?></strong>
                            </a>
                            <small>
                                <?= e(date('d M Y', strtotime((string) $post['created_at']))) ?>
                                · <?= $comments === 1 ? '1 comment' : $comments . ' comments' ?>
                                · <?= (int) $post['likes'] ?> likes
                            </small>
                        </div>
                    </div>
                </header>

                <?php if (!empty($post['image'])): ?>
                    <img
                        class="article-image"
                        src="<?= e($image) ?>"
                        alt="<?= e($title) ?>"
                        loading="eager"
                    >
                <?php endif; ?>

                <div class="article-body">
                    <?php foreach (preg_split('/\R\s*\R/', trim((string) ($post['description'] ?? ''))) as $paragraph): ?>
                        <?php if (trim($paragraph) !== ''): ?>
                            <p><?= nl2br(e(trim($paragraph))) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="article-footer">
                    <a
                        class="button button-secondary"
                        href="<?= e(public_url('comments/comments.php?blog_id=' . $id)) ?>"
                    >
                        Discuss this story
                    </a>
                </div>
            </article>

            <aside class="article-sidebar" aria-label="Related content">
                <div class="sidebar-card">
                    <h2>About the writer</h2>
                    <p>
                        <a href="<?= e($authorLink) ?>">
                            <strong><?= e($author) ?></strong>
                        </a>
                        <?php if (!empty($post['user_type'])): ?>
                            · <?= e((string) $post['user_type']) ?>
                        <?php endif; ?>.
                        Explore more stories from this writer.
                    </p>
                </div>

                <?php if ($related): ?>
                    <div class="sidebar-card">
                        <h2>Related stories</h2>
                        <div class="related-list">
                            <?php foreach ($related as $item): ?>
                                <a href="<?= e(article_url((int) $item['blog_id'], (string) $item['title'])) ?>">
                                    <strong><?= e((string) $item['title']) ?></strong>
                                    <small>
                                        <?= e(date('d M Y', strtotime((string) $item['created_at']))) ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <a class="footer-brand" href="<?= e(public_url('index.html')) ?>">
                <img src="<?= e(public_url('assets/weblogr-mark.svg')) ?>" alt="" aria-hidden="true">
                <strong>Weblogr</strong>
            </a>
            <span>Ideas worth sharing.</span>
            <div class="footer-links">
                <a href="<?= e(public_url('blog.php')) ?>">Discover</a>
                <a href="<?= e(public_url('about.html')) ?>">About</a>
                <a href="<?= e(public_url('privacy.html')) ?>">Privacy</a>
                <a href="<?= e(public_url('terms.html')) ?>">Terms</a>
            </div>
            <span>© 2026 Weblogr</span>
        </div>
    </footer>
</body>
</html>
