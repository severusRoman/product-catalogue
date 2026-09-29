<?php
/**
 * Copy this file to config/config.php and fill in your own values.
 * config/config.php is listed in .gitignore, so your real credentials are never pushed to GitHub.
 */
return [
    'db' => [
        'host'    => 'localhost',        // InfinityFree: something like sql123.infinityfree.com
        'name'    => 'catalogue_db',     // InfinityFree: something like if0_12345678_catalogue
        'user'    => 'root',             // InfinityFree: something like if0_12345678
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'Shelfwise',
        'base_url'  => '',               // '' if the site is at the domain root; '/product-catalogue' if in a sub-folder (XAMPP)
        'currency'  => '৳',
        'timezone'  => 'Asia/Dhaka',
        'debug'     => false,            // true only while developing: shows detailed errors
        'csp'       => true,             // Content-Security-Policy header. Set false if your host injects scripts.
    ],
];
