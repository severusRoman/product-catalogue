<?php
/** Friendly error page used by abort(). Expects $errorCode and $errorMessage. */
include ROOT_PATH . '/includes/header.php';
?>
<section class="container page-narrow">
    <div class="empty">
        <p class="empty__code"><?= (int) $errorCode ?></p>
        <h1 class="empty__title"><?= e($errorMessage) ?></h1>
        <p><a class="btn" href="<?= e(url('index.php')) ?>">Back to home</a></p>
    </div>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
