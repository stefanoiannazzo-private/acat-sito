<?php
/* sitemap.xml generata al volo con il dominio su cui gira il sito (vedi .htaccess) */
$pagine = [
  ['index.html', '1.0'], ['servizi.html', '0.9'], ['chi-siamo.html', '0.8'],
  ['contatti.html', '0.8'], ['privacy.html', '0.2'], ['cookie.html', '0.2'],
];
$host = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pagine as [$file, $prio]) {
    $loc = $schema . '://' . $host . '/' . ($file === 'index.html' ? '' : $file);
    $mod = is_file(__DIR__ . '/' . $file) ? date('Y-m-d', filemtime(__DIR__ . '/' . $file)) : date('Y-m-d');
    echo "  <url><loc>" . htmlspecialchars($loc) . "</loc><lastmod>$mod</lastmod><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
