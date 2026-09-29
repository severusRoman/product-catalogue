<?php
/**
 * Shared page header. Set $pageTitle and $activeNav before including.
 * current_user() is called first so the session can be refreshed before any HTML is sent.
 */
$navUser   = current_user();
$appName   = (string) config('app.name', 'Shelfwise');
$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';

$navLink = static function (string $key, string $file, string $label) use ($activeNav): string {
    $current = $activeNav === $key ? ' aria-current="page"' : '';
    return '<a class="nav__link' . ($activeNav === $key ? ' is-active' : '') . '" href="' . e(url($file)) . '"' . $current . '>' . e($label) . '</a>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' | ' . $appName : $appName . ' | Product catalogue') ?></title>
    <meta name="description" content="Browse and manage a searchable product catalogue.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($appName) ?> home">
            <svg class="brand__mark" width="26" height="26" viewBox="0 0 26 26" aria-hidden="true" focusable="false">
                <path d="M9 2h15v22H9L1 13z" fill="#ffc53d"/>
                <circle cx="7.5" cy="13" r="2" fill="#14213d"/>
            </svg>
            <span class="brand__name"><?= e($appName) ?></span>
        </a>

        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav">
            Menu
        </button>

        <nav class="nav" id="site-nav" aria-label="Main">
            <?= $navLink('home', 'index.php', 'Home') ?>
            <?= $navLink('catalogue', 'catalogue.php', 'Catalogue') ?>
            <?php if ($navUser): ?>
                <?= $navLink('dashboard', 'dashboard.php', 'My products') ?>
                <a class="btn btn--small" href="<?= e(url('product_form.php')) ?>">Add product</a>
                <span class="nav__user"><?= e($navUser['name']) ?><?= is_admin($navUser) ? ' (admin)' : '' ?></span>
                <form class="nav__logout" method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost btn--small" type="submit">Log out</button>
                </form>
            <?php else: ?>
                <?= $navLink('login', 'login.php', 'Log in') ?>
                <a class="btn btn--small" href="<?= e(url('register.php')) ?>">Create account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main">
    <?php $flashes = take_flash(); ?>
    <?php if ($flashes): ?>
        <div class="container flash-wrap">
            <?php foreach ($flashes as $f): ?>
                <p class="flash flash--<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
