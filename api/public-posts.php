<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../includes/public_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(12, max(1, (int) ($_GET['per_page'] ?? 12)));
$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));

$allowed = [
    'education',
    'technology',
    'travel',
    'food',
    'fashion',
    'sport',
    'other',
];

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
    $conditions[] = 'b.category=?';
    $params[] = $category;
    $types .= 's';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$count = $con->prepare(
    'SELECT COUNT(*) total FROM blogs b LEFT JOIN users u ON u.user_id=b.user_id' . $where
);

if ($params) {
    $count->bind_param($types, ...$params);
}

$count->execute();
$total = (int) ($count->get_result()->fetch_assoc()['total'] ?? 0);
$count->close();

$pages = max(1, (int) ceil($total / $perPage));

if ($page > $pages) {
    echo json_encode([
        'items' => [],
        'page' => $page,
        'next_page' => null,
        'has_more' => false,
        'total' => $total,
    ]);
    $con->close();
    exit;
}

$offset = ($page - 1) * $perPage;
$sql = 'SELECT b.blog_id,b.title,b.created_date,b.description,b.category,b.image,b.user_id,u.username'
    . $where
    . ' ORDER BY b.created_date DESC LIMIT ? OFFSET ?';

$feed = $params;
$feed[] = $perPage;
$feed[] = $offset;

$stmt = $con->prepare($sql);
$stmt->bind_param($types . 'ii', ...$feed);
$stmt->execute();
$result = $stmt->get_result();
$items = [];

while ($post = $result->fetch_assoc()) {
    $title = (string) $post['title'];
    $image = !empty($post['image'])
        ? '<img class="story-image" src="images/' . rawurlencode((string) $post['image']) . '" alt="" loading="lazy">'
        : '<div class="story-image" aria-hidden="true"></div>';
    $author = $post['user_id']
        ? '<a href="' . e(author_url((int) $post['user_id'], (string) $post['username'])) . '">@' . e((string) $post['username']) . '</a>'
        : 'Weblogr writer';
    $html = '<article class="story-card"><a href="' . e(article_url((int) $post['blog_id'], $title)) . '" aria-label="Read ' . e($title) . '">' . $image . '</a><div class="story-card-body"><span class="category">' . e(strtoupper((string) $post['category'])) . '</span><h2><a href="' . e(article_url((int) $post['blog_id'], $title)) . '">' . e($title) . '</a></h2><p>' . e(excerpt((string) ($post['description'] ?? ''))) . '</p><div class="story-meta"><span>' . $author . '</span><span>' . e(date('d M Y', strtotime((string) $post['created_date']))) . '</span></div></div></article>';
    $items[] = $html;
}

$stmt->close();
$con->close();

$next = $page < $pages ? $page + 1 : null;

echo json_encode([
    'items' => $items,
    'page' => $page,
    'next_page' => $next,
    'has_more' => $next !== null,
    'total' => $total,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
