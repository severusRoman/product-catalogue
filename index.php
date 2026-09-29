<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle  = 'Home';
$activeNav  = 'home';
$categories = db()->query(
    'SELECT c.id, c.name, COUNT(p.id) AS total
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name ORDER BY c.name'
)->fetchAll();
$latest = latest_products(8);

include ROOT_PATH . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero__inner">
        <h1 class="hero__title">Every product, priced and in stock.</h1>
        <p class="hero__lead">Search the catalogue by name, category or price. Create an account to list and manage your own products.</p>

        <form class="hero-search" action="<?= e(url('catalogue.php')) ?>" method="get" role="search">
            <label class="visually-hidden" for="hero-q">Search products</label>
            <input class="hero-search__input" id="hero-q" type="search" name="q" maxlength="100" placeholder="Try &quot;headphones&quot; or &quot;notebook&quot;">
            <label class="visually-hidden" for="hero-cat">Category</label>
            <select class="hero-search__select" id="hero-cat" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn--accent" type="submit">Search products</button>
        </form>
    </div>
</section>

<section class="container section">
    <div class="section__head">
        <h2>Shop by category</h2>
    </div>
    <ul class="tag-list">
        <?php foreach ($categories as $c): ?>
            <li>
                <a class="tag-chip" href="<?= e(url('catalogue.php?category=' . (int) $c['id'])) ?>">
                    <?= e($c['name']) ?> <span class="tag-chip__count"><?= (int) $c['total'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="container section">
    <div class="section__head">
        <h2>Latest arrivals</h2>
        <a class="text-link" href="<?= e(url('catalogue.php')) ?>">See the full catalogue</a>
    </div>

    <?php if ($latest): ?>
        <div class="grid">
            <?php foreach ($latest as $p) { render_product_card($p); } ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h3 class="empty__title">The catalogue is empty</h3>
            <p>Be the first to list a product.</p>
            <p><a class="btn" href="<?= e(url('register.php')) ?>">Create an account</a></p>
        </div>
    <?php endif; ?>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
