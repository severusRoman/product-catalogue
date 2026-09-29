<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$product = $id > 0 ? find_product($id) : null;
if ($product === null) {
    abort(404, 'That product could not be found. It may have been removed.');
}

$user      = current_user();
$canManage = can_manage_product($user, $product);
$related   = related_products((int) $product['category_id'], (int) $product['id']);
[$stockClass, $stockText] = stock_status((int) $product['stock']);
$tone      = 'tone-' . (((int) $product['id']) % 6 + 1);
$initial   = mb_strtoupper(mb_substr((string) $product['name'], 0, 1));

$pageTitle = $product['name'];
$activeNav = 'catalogue';
include ROOT_PATH . '/includes/header.php';
?>
<section class="container section">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url('catalogue.php')) ?>">Catalogue</a>
        <a href="<?= e(url('catalogue.php?category=' . (int) $product['category_id'])) ?>"><?= e($product['category_name']) ?></a>
    </nav>

    <div class="detail">
        <div class="detail__media <?= $tone ?>">
            <?php if (!empty($product['image_path'])): ?>
                <img src="<?= e(url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>">
            <?php else: ?>
                <span class="detail__initial"><?= e($initial) ?></span>
            <?php endif; ?>
        </div>

        <div class="detail__info">
            <p class="card__category"><?= e($product['category_name']) ?></p>
            <h1 class="detail__title"><?= e($product['name']) ?></h1>
            <p class="detail__price"><span class="price-tag price-tag--large"><?= e(format_price($product['price'])) ?></span></p>
            <p class="stock stock--<?= e($stockClass) ?>"><?= e($stockText) ?><?= (int) $product['stock'] > 5 ? ' (' . (int) $product['stock'] . ' available)' : '' ?></p>

            <div class="detail__desc">
                <?php if (!empty($product['description'])): ?>
                    <p><?= nl2br(e($product['description'])) ?></p>
                <?php else: ?>
                    <p class="muted">The seller has not added a description yet.</p>
                <?php endif; ?>
            </div>

            <dl class="facts">
                <div><dt>Listed by</dt><dd><?= e($product['seller_name']) ?></dd></div>
                <div><dt>Added</dt><dd><?= e(format_date($product['created_at'])) ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(format_date($product['updated_at'])) ?></dd></div>
            </dl>

            <?php if ($canManage): ?>
                <div class="actions">
                    <a class="btn" href="<?= e(url('product_form.php?id=' . (int) $product['id'])) ?>">Edit product</a>
                    <form method="post" action="<?= e(url('product_delete.php')) ?>" data-confirm="Delete &quot;<?= e($product['name']) ?>&quot;? This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                        <button class="btn btn--danger" type="submit">Delete product</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($related): ?>
<section class="container section">
    <div class="section__head"><h2>More in <?= e($product['category_name']) ?></h2></div>
    <div class="grid grid--four">
        <?php foreach ($related as $p) { render_product_card($p); } ?>
    </div>
</section>
<?php endif; ?>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
