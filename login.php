<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];
$email  = '';
$next   = safe_next(input_string($_GET, 'next', 200));

if (is_post()) {
    csrf_check();

    $email    = strtolower(input_string($_POST, 'email', 254));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $next     = safe_next(input_string($_POST, 'next', 200));

    if ($email === '' || $password === '') {
        $errors['form'] = 'Enter your email and password.';
    } elseif (login_is_blocked($email)) {
        $errors['form'] = 'Too many failed attempts. Wait 15 minutes and try again.';
    } else {
        $user = verify_credentials($email, $password);
        if ($user === null) {
            record_failed_login($email);
            $errors['form'] = 'That email and password do not match an account.';
        } else {
            clear_failed_logins($email);
            login_user($user);
            flash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect($next);
        }
    }
}

$pageTitle = 'Log in';
$activeNav = 'login';
include ROOT_PATH . '/includes/header.php';
?>
<section class="container page-narrow section">
    <h1>Log in</h1>
    <p class="muted">Log in to manage your products.</p>

    <?php if (isset($errors['form'])): ?>
        <p class="flash flash--error" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('login.php')) ?>" data-validate="login" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="<?= field_class($errors, 'email') ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="190" value="<?= e($email) ?>" required autofocus>
            <?= field_error($errors, 'email') ?>
        </div>

        <div class="<?= field_class($errors, 'password') ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
            <?= field_error($errors, 'password') ?>
        </div>

        <button class="btn btn--block" type="submit">Log in</button>
    </form>

    <p class="form-alt">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
