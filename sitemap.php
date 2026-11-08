<?php
require 'includes/init.php';

// sitemap.php: the public pages for search engines, including every tour and visa destination
header('Content-Type: application/xml; charset=utf-8');

$urls = ['', 'flights.php', 'hotels.php', 'visa.php', 'tours.php', 'about.php', 'contact.php'];
foreach (db_all("SELECT slug FROM tour_packages WHERE is_published = 1") as $t) {
    $urls[] = 'tour.php?slug=' . $t['slug'];
}
foreach (db_all("SELECT slug FROM visa_destinations WHERE is_active = 1") as $v) {
    $urls[] = 'visa.php?country=' . $v['slug'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . e(SITE_URL . '/' . $u) . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";
