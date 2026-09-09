<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/database/db.php';
require_once __DIR__ . '/includes/public_helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    [
        'loc' => public_url('index.html'),
        'changefreq' => 'weekly',
        'priority' => '1.0',
    ],
    [
        'loc' => public_url('blog.php'),
        'changefreq' => 'daily',
        'priority' => '0.9',
    ],
    [
        'loc' => public_url('about.html'),
        'changefreq' => 'monthly',
        'priority' => '0.5',
    ],
    [
        'loc' => public_url('privacy.html'),
        'changefreq' => 'yearly',
        'priority' => '0.2',
    ],
    [
        'loc' => public_url('terms.html'),
        'changefreq' => 'yearly',
        'priority' => '0.2',
    ],
];

$r = $con->query('SELECT blog_id, title, created_at FROM blogs ORDER BY created_at DESC');

while ($row = $r->fetch_assoc()) {
    $urls[] = [
        'loc' => article_url((int) $row['blog_id'], (string) $row['title']),
        'lastmod' => date('c', strtotime((string) $row['created_at'])),
        'changefreq' => 'monthly',
        'priority' => '0.8',
    ];
}

$r = $con->query(
    'SELECT u.user_id, u.username, MAX(b.created_at) lastmod
     FROM users u
     INNER JOIN blogs b ON b.user_id = u.user_id
     GROUP BY u.user_id, u.username'
);

while ($row = $r->fetch_assoc()) {
    $urls[] = [
        'loc' => author_url((int) $row['user_id'], (string) $row['username']),
        'lastmod' => date('c', strtotime((string) $row['lastmod'])),
        'changefreq' => 'weekly',
        'priority' => '0.6',
    ];
}

$con->close();

echo '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

foreach ($urls as $url) {
    echo '<url><loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';

    if (isset($url['lastmod'])) {
        echo '<lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
    }

    echo '<changefreq>' . $url['changefreq'] . '</changefreq><priority>' . $url['priority'] . '</priority></url>';
}

echo '</urlset>';
