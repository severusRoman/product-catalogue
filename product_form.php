<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

// ?id=... means edit, no id means create. Ownership is checked BEFORE showing or saving anything.
$id      = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = null;
if ($id > 0) {
    $product = find_product($id);
    if ($product === null) {
        abort(404, 'That product could not be found.');
    }
    if (!can_manage_product($user, $product)) {
        abort(403, 'You can only edit products that you added.');
    }
}
$isEdit = $product !== null;

$errors = [];
$values = $isEdit
    ? [
        'name'        => $product['name'],
        'description' => (string) $product['description'],
        'price'       => $product['price'],
        'stock'       => (string) $product['stock'],
        'category_id' => (int) $product['category_id'],
    ]
    : ['name' => '', 'description' => '', 'price' => '', 'stock' => '1', 'category_id' => 0];

if (is_post()) {
    csrf_check();

    [$values, $errors] = validate_product_input($_POST);
    $newImage = store_product_image($_FILES['image'] ?? null, $errors);
    if ($errors) {
        delete_product_image($newImage); // do not keep an upload from a rejected submission
        $newImage = null;
    }

    if (!$errors) {
        $removeImage = ($_POST['remove_image'] ?? '') === '1';

        if ($isEdit) {
            $imagePath = $product['image_path'];
            if ($newImage !== null) {
                $imagePath = $newImage;
            } elseif ($removeImage) {
                $imagePath = null;
            }
            try {
                update_product((int) $product['id'], $values, $imagePath);
            } catch (Throwable $t) {
                delete_product_image($newImage);
                throw $t;
            }
            if ($imagePath !== $product['image_path']) {
                delete_product_image($product['image_path']); // remove the replaced / removed file
            }
            flash('success', 'Saved your changes to "' . $values['name'] . '".');
            redirect('product.php?id=' . (int) $product['id']);
        }

        try {
            $newId = create_product((int) $user['id'], $values, $newImage);
        } catch (Throwable $t) {
            delete_product_image($newImage);
            throw $t;
        }
        flash('success', 'Added "' . $values['name'] . '" to the catalogue.');
        redirect('product.php?id=' . $newId);
    }
}

$categories = all_categories();
$pageTitle  = $isEdit ? 'Edit product' : 'Add product';
$activeNav  = 'dashboard';
include ROOT_PATH . '/includes/header.php';
?>
<section class="container page-medium section">
    <h1><?= $isEdit ? 'Edit product' : 'Add a product' ?></h1>
    <p class="muted"><?= $isEdit ? 'Update the details below and save.' : 'Fill in the details. It appears in the public catalogue as soon as you save.' ?></p>

    <?php if ($errors): ?>
        <p class="flash flash--error" role="alert">Some details need fixing. Check the highlighted fields below.</p>
    <?php endif; ?>

    <form class="form" method="post" enctype="multipart/form-data"
          action="<?= e(url('product_form.php' . ($isEdit ? '?id=' . (int) $product['id'] : ''))) ?>"
          data-validate="product" novalidate>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><?php endif; ?>

        <div class="<?= field_class($errors, 'name') ?>">
            <label for="name">Product name</label>
            <input id="name" name="name" type="text" maxlength="120" value="<?= e($values['name']) ?>" required>
            <?= field_error($errors, 'name') ?>
        </div>

        <div class="<?= field_class($errors, 'category_id') ?>">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Choose a category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $values['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </div>

        <div class="form__row">
            <div class="<?= field_class($errors, 'price') ?>">
                <label for="price">Price (<?= e((string) config('app.currency', '৳')) ?>)</label>
                <input id="price" name="price" type="text" inputmode="decimal" value="<?= e($values['price']) ?>" placeholder="1250.00" required>
                <?= field_error($errors, 'price') ?>
            </div>
            <div class="<?= field_class($errors, 'stock') ?>">
                <label for="stock">Stock quantity</label>
                <input id="stock" name="stock" type="text" inputmode="numeric" value="<?= e($values['stock']) ?>" required>
                <?= field_error($errors, 'stock') ?>
            </div>
        </div>

        <div class="<?= field_class($errors, 'description') ?>">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" rows="5" maxlength="2000"><?= e($values['description']) ?></textarea>
            <p class="field__hint"><span data-counter-for="description">0</span> / 2000 characters</p>
            <?= field_error($errors, 'description') ?>
        </div>

        <div class="<?= field_class($errors, 'image') ?>">
            <label for="image">Product image <span class="optional">(optional)</span></label>
            <?php if ($isEdit && !empty($product['image_path'])): ?>
                <div class="current-image">
                    <img src="<?= e(url($product['image_path'])) ?>" alt="Current image of <?= e($product['name']) ?>">
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> <span>Remove current image</span></label>
                </div>
            <?php endif; ?>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <p class="field__hint">JPG, PNG or WebP, up to 2 MB.</p>
            <img class="image-preview" id="image-preview" alt="Preview of the selected image" hidden>
            <?= field_error($errors, 'image') ?>
        </div>

        <div class="form__actions">
            <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
            <a class="btn btn--ghost" href="<?= e(url($isEdit ? 'product.php?id=' . (int) $product['id'] : 'dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
