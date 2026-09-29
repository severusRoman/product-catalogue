<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Catalogue';
$activeNav = 'catalogue';

$filters    = parse_product_filters($_GET);
$requested  = max(1, (int) ($_GET['page'] ?? 1));
$result     = search_products($filters, $requested);
$categories = all_categories();
$sorts      = product_sort_options();
$filtered   = has_active_filters($filters);

include ROOT_PATH . '/includes/header.php';
?>
<section class="container section">
    <div class="section__head">
        <h1>Catalogue</h1>
        <p class="muted"><?= (int) $result['total'] ?> <?= $result['total'] === 1 ? 'product' : 'products' ?><?= $filtered ? ' match your filters' : '' ?></p>
    </div>

    <form class="filters" method="get" action="<?= e(url('catalogue.php')) ?>" role="search">
        <div class="field filters__q">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Name or description">
        </div>
        <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category" data-autosubmit>
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $filters['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--price">
            <label for="min">Min price</label>
            <input id="min" type="text" inputmode="decimal" name="min" value="<?= e($filters['min'] ?? '') ?>" placeholder="0">
        </div>
        <div class="field field--price">
            <label for="max">Max price</label>
            <input id="max" type="text" inputmode="decimal" name="max" value="<?= e($filters['max'] ?? '') ?>" placeholder="Any">
        </div>
        <div class="field">
            <label for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach ($sorts as $key => $option): ?>
                    <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($option[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="check filters__stock">
            <input type="checkbox" name="in_stock" value="1" <?= $filters['in_stock'] ? 'checked' : '' ?> data-autosubmit>
            <span>In stock only</span>
        </label>
        <div class="filters__actions">
            <button class="btn" type="submit">Apply filters</button>
            <?php if ($filtered || $filters['sort'] !== 'newest'): ?>
                <a class="btn btn--ghost" href="<?= e(url('catalogue.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($result['items']): ?>
        <div class="grid">
            <?php foreach ($result['items'] as $p) { render_product_card($p); } ?>
        </div>
        <?php render_pager($result['page'], $result['pages']); ?>
    <?php else: ?>
        <div class="empty">
            <h2 class="empty__title">No products match</h2>
            <p>Try a shorter search, another category or a wider price range.</p>
            <p><a class="btn" href="<?= e(url('catalogue.php')) ?>">Clear all filters</a></p>
        </div>
    <?php endif; ?>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
