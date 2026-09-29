<?php
/**
 * Product logic: queries (search / filter / sort / paginate), validation, image upload, card markup.
 * All SQL uses prepared statements with bound parameters.
 */
declare(strict_types=1);

const PRODUCTS_PER_PAGE = 9;
const MAX_IMAGE_BYTES   = 2 * 1024 * 1024; // 2 MB
const PRICE_PATTERN     = '/^\d{1,8}(\.\d{1,2})?$/';

// ---- Lookups ----------------------------------------------------------------------

function all_categories(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    }
    return $cache;
}

/** Allowed sort orders. The value comes from this whitelist, never from user input. */
function product_sort_options(): array
{
    return [
        'newest'     => ['Newest first',       'p.created_at DESC, p.id DESC'],
        'price_asc'  => ['Price: low to high', 'p.price ASC, p.id DESC'],
        'price_desc' => ['Price: high to low', 'p.price DESC, p.id DESC'],
        'name'       => ['Name: A to Z',       'p.name ASC, p.id DESC'],
    ];
}

function stock_status(int $stock): array
{
    if ($stock <= 0) {
        return ['out', 'Out of stock'];
    }
    if ($stock <= 5) {
        return ['low', 'Only ' . $stock . ' left'];
    }
    return ['in', 'In stock'];
}

// ---- Search / filter ----------------------------------------------------------------

/**
 * Turn raw query-string values into a clean, validated filter array.
 * Anything invalid is quietly dropped rather than passed to the database.
 */
function parse_product_filters(array $src): array
{
    $validCategoryIds = array_map(static fn($c) => (int) $c['id'], all_categories());

    $category = (int) ($src['category'] ?? 0);
    if (!in_array($category, $validCategoryIds, true)) {
        $category = 0;
    }

    $min = input_string($src, 'min', 12);
    $max = input_string($src, 'max', 12);
    $min = preg_match(PRICE_PATTERN, $min) === 1 ? $min : null;
    $max = preg_match(PRICE_PATTERN, $max) === 1 ? $max : null;
    if ($min !== null && $max !== null && (float) $min > (float) $max) {
        [$min, $max] = [$max, $min];
    }

    $sort = input_string($src, 'sort', 20);
    if (!array_key_exists($sort, product_sort_options())) {
        $sort = 'newest';
    }

    return [
        'q'        => input_string($src, 'q', 100),
        'category' => $category,
        'min'      => $min,
        'max'      => $max,
        'in_stock' => (input_string($src, 'in_stock', 1) === '1'),
        'sort'     => $sort,
    ];
}

/** True when the visitor has applied at least one search term or filter. */
function has_active_filters(array $filters): bool
{
    return $filters['q'] !== '' || $filters['category'] > 0 || $filters['min'] !== null
        || $filters['max'] !== null || $filters['in_stock'];
}

/**
 * Search products with filters, sorting and pagination.
 * @param int|null $ownerId limit results to one user's products (dashboard), or null for everyone's.
 * @return array{items:array,total:int,page:int,pages:int}
 */
function search_products(array $filters, int $requestedPage, int $perPage = PRODUCTS_PER_PAGE, ?int $ownerId = null): array
{
    $where  = [];
    $params = [];

    if ($ownerId !== null) {
        $where[]  = 'p.user_id = ?';
        $params[] = $ownerId;
    }
    if ($filters['q'] !== '') {
        // Escape LIKE wildcards typed by the visitor so "50%" searches for the literal text.
        $like     = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $where[]  = "(p.name LIKE ? ESCAPE '!' OR p.description LIKE ? ESCAPE '!')";
        $params[] = $like;
        $params[] = $like;
    }
    if ($filters['category'] > 0) {
        $where[]  = 'p.category_id = ?';
        $params[] = $filters['category'];
    }
    if ($filters['min'] !== null) {
        $where[]  = 'p.price >= ?';
        $params[] = $filters['min'];
    }
    if ($filters['max'] !== null) {
        $where[]  = 'p.price <= ?';
        $params[] = $filters['max'];
    }
    if ($filters['in_stock']) {
        $where[] = 'p.stock > 0';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $bind = static function (PDOStatement $stmt, array $values): void {
        foreach (array_values($values) as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    };

    $countStmt = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $bind($countStmt, $params);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $pager     = paginate($total, $perPage, $requestedPage);
    $orderSql  = product_sort_options()[$filters['sort']][1];

    $stmt = db()->prepare(
        "SELECT p.id, p.user_id, p.category_id, p.name, p.description, p.price, p.stock, p.image_path,
                p.created_at, c.name AS category_name, u.name AS seller_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u      ON u.id = p.user_id
         $whereSql
         ORDER BY $orderSql
         LIMIT ? OFFSET ?"
    );
    $bind($stmt, array_merge($params, [$perPage, $pager['offset']]));
    $stmt->execute();

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $pager['page'], 'pages' => $pager['pages']];
}

function latest_products(int $limit): array
{
    $result = search_products(parse_product_filters([]), 1, $limit);
    return $result['items'];
}

function find_product(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, u.name AS seller_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u      ON u.id = p.user_id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function related_products(int $categoryId, int $excludeId, int $limit = 4): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.user_id, p.category_id, p.name, p.price, p.stock, p.image_path, c.name AS category_name
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = ? AND p.id <> ?
         ORDER BY p.created_at DESC, p.id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(2, $excludeId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Numbers shown at the top of the dashboard. */
function product_stats(?int $ownerId): array
{
    $sql    = 'SELECT COUNT(*) AS total,
                      COALESCE(SUM(stock > 5), 0)              AS in_stock,
                      COALESCE(SUM(stock BETWEEN 1 AND 5), 0)  AS low_stock,
                      COALESCE(SUM(stock = 0), 0)              AS out_of_stock,
                      COALESCE(SUM(price * stock), 0)          AS inventory_value
               FROM products';
    $params = [];
    if ($ownerId !== null) {
        $sql     .= ' WHERE user_id = ?';
        $params[] = $ownerId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// ---- Create / update / delete ---------------------------------------------------------

function create_product(int $userId, array $data, ?string $imagePath): int
{
    $stmt = db()->prepare(
        'INSERT INTO products (user_id, category_id, name, description, price, stock, image_path)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId, $data['category_id'], $data['name'],
        $data['description'] !== '' ? $data['description'] : null,
        $data['price'], $data['stock'], $imagePath,
    ]);
    return (int) db()->lastInsertId();
}

function update_product(int $id, array $data, ?string $imagePath): void
{
    $stmt = db()->prepare(
        'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image_path = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $data['category_id'], $data['name'],
        $data['description'] !== '' ? $data['description'] : null,
        $data['price'], $data['stock'], $imagePath, $id,
    ]);
}

function delete_product(int $id): void
{
    $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
}

// ---- Validation -------------------------------------------------------------------------

/**
 * Validate the product form. The same rules are checked in the browser (assets/js/app.js) for
 * convenience, but only THIS server-side check is trusted.
 * @return array{0:array,1:array} [clean values, errors keyed by field]
 */
function validate_product_input(array $post): array
{
    $errors = [];

    $name        = input_string($post, 'name', 200);
    $description = input_string($post, 'description', 3000);
    $price       = input_string($post, 'price', 20);
    $stock       = input_string($post, 'stock', 12);
    $category    = (int) ($post['category_id'] ?? 0);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Enter a product name between 2 and 120 characters.';
    }

    $validCategoryIds = array_map(static fn($c) => (int) $c['id'], all_categories());
    if (!in_array($category, $validCategoryIds, true)) {
        $errors['category_id'] = 'Choose a category from the list.';
    }

    if (preg_match(PRICE_PATTERN, $price) !== 1) {
        $errors['price'] = 'Enter a price like 1250 or 1250.50 (up to 2 decimal places).';
    } elseif ((float) $price <= 0) {
        $errors['price'] = 'The price must be greater than zero.';
    }

    if (preg_match('/^\d{1,7}$/', $stock) !== 1) {
        $errors['stock'] = 'Enter the stock as a whole number, 0 or more.';
    }

    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'Keep the description under 2000 characters.';
    }

    $clean = [
        'name'        => $name,
        'description' => $description,
        'price'       => $price,
        'stock'       => $stock,
        'category_id' => $category,
    ];
    return [$clean, $errors];
}

// ---- Image upload -------------------------------------------------------------------------

/**
 * Validate and store an uploaded image.
 * Checks: upload error code, size, real MIME type (from file contents, not the filename), and
 * that PHP can read it as an image. The file is saved under a random name, never the original.
 * @return string|null stored relative path (e.g. "uploads/products/ab12....jpg"), or null if nothing was uploaded / it failed
 */
function store_product_image(?array $file, array &$errors): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'That image is too large. The limit is 2 MB.'
            : 'The image could not be uploaded. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        $errors['image'] = 'That image is too large. The limit is 2 MB.';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors['image'] = 'The image could not be uploaded. Please try again.';
        return null;
    }

    // The type is read from the file's actual contents, never from its name or the browser-supplied type.
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info    = @getimagesize($file['tmp_name']);           // core PHP, available on every host
    $mime    = is_array($info) ? ($info['mime'] ?? null) : null;
    if ($mime !== null && class_exists('finfo')) {          // second opinion when the fileinfo extension exists
        if ((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== $mime) {
            $mime = null;
        }
    }
    if (!is_string($mime) || !isset($allowed[$mime])) {
        $errors['image'] = 'Upload a JPG, PNG or WebP image.';
        return null;
    }

    $dir = ROOT_PATH . '/uploads/products';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        $errors['image'] = 'The image could not be saved on the server.';
        return null;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        $errors['image'] = 'The image could not be saved on the server.';
        return null;
    }
    return 'uploads/products/' . $filename;
}

/** Delete a stored image. Only paths we generated ourselves are accepted (blocks path traversal). */
function delete_product_image(?string $path): void
{
    if ($path === null || preg_match('#^uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$#', $path) !== 1) {
        return;
    }
    $full = ROOT_PATH . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

// ---- Markup --------------------------------------------------------------------------------

/** Print one product card (used on Home, Catalogue and the product page). */
function render_product_card(array $p): void
{
    $tone   = 'tone-' . (((int) $p['id']) % 6 + 1);
    $link   = url('product.php?id=' . (int) $p['id']);
    [$stockClass, $stockText] = stock_status((int) $p['stock']);
    $initial = mb_strtoupper(mb_substr((string) $p['name'], 0, 1));
    ?>
    <article class="card">
        <a class="card__media <?= $tone ?>" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
            <?php if (!empty($p['image_path'])): ?>
                <img src="<?= e(url($p['image_path'])) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="card__initial"><?= e($initial) ?></span>
            <?php endif; ?>
            <span class="price-tag"><?= e(format_price($p['price'])) ?></span>
        </a>
        <div class="card__body">
            <p class="card__category"><?= e($p['category_name']) ?></p>
            <h3 class="card__title"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
            <p class="stock stock--<?= e($stockClass) ?>"><?= e($stockText) ?></p>
        </div>
    </article>
    <?php
}

/** Print the previous / next / numbered pager for the current listing. */
function render_pager(int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    $window = range(max(1, $page - 2), min($pages, $page + 2));
    ?>
    <nav class="pager" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a class="pager__link" href="<?= e(page_url($page - 1)) ?>" rel="prev">Previous</a>
        <?php endif; ?>
        <?php foreach ($window as $n): ?>
            <?php if ($n === $page): ?>
                <span class="pager__link is-current" aria-current="page"><?= $n ?></span>
            <?php else: ?>
                <a class="pager__link" href="<?= e(page_url($n)) ?>"><?= $n ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($page < $pages): ?>
            <a class="pager__link" href="<?= e(page_url($page + 1)) ?>" rel="next">Next</a>
        <?php endif; ?>
    </nav>
    <?php
}
