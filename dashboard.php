<?php
require __DIR__ . '/includes/bootstrap.php';

$user    = require_login();
$isAdmin = is_admin($user);
$ownerId = $isAdmin ? null : (int) $user['id']; // admins see everyone's products, users only their own

$filters    = parse_product_filters($_GET);
$requested  = max(1, (int) ($_GET['page'] ?? 1));
$result     = search_products($filters, $requested, 10, $ownerId);
$stats      = product_stats($ownerId);
$categories = all_categories();
$sorts      = product_sort_options();
$filtered   = has_active_filters($filters);

$pageTitle = $isAdmin ? 'All products' : 'My products';
$activeNav = 'dashboard';
include ROOT_PATH . '/includes/header.php';
?>
<section class="container section">
    <div class="section__head">
        <h1><?= $isAdmin ? 'All products' : 'My products' ?></h1>
        <a class="btn" href="<?= e(url('product_form.php')) ?>">Add product</a>
    </div>
    <?php if ($isAdmin): ?>
        <p class="muted">You are logged in as an admin, so you can edit and delete every product.</p>
    <?php endif; ?>

    <dl class="stats">
        <div class="stats__item"><dt>Products</dt><dd><?= (int) $stats['total'] ?></dd></div>
        <div class="stats__item"><dt>In stock</dt><dd><?= (int) $stats['in_stock'] ?></dd></div>
        <div class="stats__item"><dt>Running low (5 or fewer)</dt><dd><?= (int) $stats['low_stock'] ?></dd></div>
        <div class="stats__item"><dt>Out of stock</dt><dd><?= (int) $stats['out_of_stock'] ?></dd></div>
        <div class="stats__item"><dt>Stock value</dt><dd><?= e(format_price($stats['inventory_value'])) ?></dd></div>
    </dl>

    <form class="filters filters--compact" method="get" action="<?= e(url('dashboard.php')) ?>" role="search">
        <div class="field filters__q">
            <label for="q">Search my products</label>
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
        <div class="field">
            <label for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach ($sorts as $key => $option): ?>
                    <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($option[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filters__actions">
            <button class="btn" type="submit">Search</button>
            <?php if ($filtered || $filters['sort'] !== 'newest'): ?>
                <a class="btn btn--ghost" href="<?= e(url('dashboard.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($result['items']): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col" class="num">Stock</th>
                        <?php if ($isAdmin): ?><th scope="col">Listed by</th><?php endif; ?>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['items'] as $p): ?>
                    <?php [$sc, $st] = stock_status((int) $p['stock']); ?>
                    <tr>
                        <td data-label="Product"><a class="table__name" href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>"><?= e($p['name']) ?></a></td>
                        <td data-label="Category"><?= e($p['category_name']) ?></td>
                        <td data-label="Price" class="num"><?= e(format_price($p['price'])) ?></td>
                        <td data-label="Stock" class="num"><span class="stock stock--<?= e($sc) ?>"><?= (int) $p['stock'] ?></span></td>
                        <?php if ($isAdmin): ?><td data-label="Listed by"><?= e($p['seller_name']) ?></td><?php endif; ?>
                        <td class="table__actions">
                            <a class="btn btn--small btn--ghost" href="<?= e(url('product_form.php?id=' . (int) $p['id'])) ?>">Edit</a>
                            <form method="post" action="<?= e(url('product_delete.php')) ?>" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn--small btn--danger-ghost" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_pager($result['page'], $result['pages']); ?>
    <?php elseif ($filtered): ?>
        <div class="empty">
            <h2 class="empty__title">No products match</h2>
            <p>Try a different search or clear the filters.</p>
            <p><a class="btn" href="<?= e(url('dashboard.php')) ?>">Clear filters</a></p>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2 class="empty__title">You have not added any products yet</h2>
            <p>Add your first product and it will appear in the public catalogue.</p>
            <p><a class="btn" href="<?= e(url('product_form.php')) ?>">Add your first product</a></p>
        </div>
    <?php endif; ?>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
