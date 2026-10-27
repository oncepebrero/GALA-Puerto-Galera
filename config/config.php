<?php
/**
 * GALÁ configuration. Do not put real passwords in this file.
 * To override anything on your machine, create config/config.local.php that returns an array
 * with only the keys you want to change (it is ignored by version control).
 *
 * Laragon defaults: MySQL user "root", empty password, port 3306.
 */
$config = [
    'site_name'   => 'GALÁ',
    'site_title'  => 'GALÁ-Explore. Enjoy. Tara Galera!',
    'tagline'     => 'Explore. Enjoy. Tara Galera!',
    'subtitle'    => 'Puerto Galera Tourism Discovery System',
    'base_url'    => null,            // null = auto-detect (works for http://gala.test and http://localhost/gala)
    'site_url'    => '',              // full public URL for canonical/share tags, e.g. https://gala.example.ph (optional)
    'debug'       => false,           // true shows PHP errors while you develop; keep false when public

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'gala',
        'user' => 'root',
        'pass' => '',
    ],

    // Staff account setup. On a public server set a key; it is then required to create the first account.
    'setup_key' => '',

    // Images (see README, "Replacing placeholder images")
    'hero_image'      => 'assets/img/hero/puerto-galera-hero.jpg',
    'hero_image_small'=> 'assets/img/hero/puerto-galera-hero-1200.jpg',

    // Feedback safeguards
    'feedback' => [
        'max_comment'   => 600,
        'min_seconds'   => 4,    // forms submitted faster than this are treated as bots
        'per_hour'      => 5,    // submissions allowed per visitor per hour
        'max_photo_mb'  => 5,    // largest photo accepted (also limited by PHP's upload_max_filesize)
        'auto_publish'  => false // never turn on without a moderation plan
    ],

    // Content bookkeeping shown on the site
    'reference_site' => 'https://www.experiencepuertogalera.com/',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, (array) require $local);
}
if (($v = getenv('GALA_DB_HOST')) !== false) $config['db']['host'] = $v;
if (($v = getenv('GALA_DB_NAME')) !== false) $config['db']['name'] = $v;
if (($v = getenv('GALA_DB_USER')) !== false) $config['db']['user'] = $v;
if (($v = getenv('GALA_DB_PASS')) !== false) $config['db']['pass'] = $v;
if (($v = getenv('GALA_SETUP_KEY')) !== false) $config['setup_key'] = $v;
return $config;
