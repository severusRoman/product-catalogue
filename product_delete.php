<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

// Deleting changes data, so it is POST-only and needs a valid CSRF token.
if (!is_post()) {
    redirect('dashboard.php');
}
csrf_check();

$id      = (int) ($_POST['id'] ?? 0);
$product = $id > 0 ? find_product($id) : null;
if ($product === null) {
    abort(404, 'That product could not be found. It may already have been deleted.');
}
if (!can_manage_product($user, $product)) {
    abort(403, 'You can only delete products that you added.');
}

delete_product((int) $product['id']);
delete_product_image($product['image_path']);

flash('success', 'Deleted "' . $product['name'] . '".');
redirect('dashboard.php');
