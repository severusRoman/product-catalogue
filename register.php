<?php
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];
$old    = ['name' => '', 'email' => ''];

if (is_post()) {
    csrf_check();

    $name     = input_string($_POST, 'name', 100);
    $email    = strtolower(input_string($_POST, 'email', 254));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $old      = ['name' => $name, 'email' => $email];

    if (preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'-]{1,59}$/u', $name) !== 1) {
        $errors['name'] = 'Enter your name using 2 to 60 letters (spaces, dots, hyphens and apostrophes are fine).';
    }
    if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address, like name@example.com.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use 72 characters or fewer.';
    } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
        $errors['password'] = 'Include at least one letter and one number.';
    }
    if (!isset($errors['password']) && !hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        }
    }

    if (!$errors) {
        try {
            $insert = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) db()->lastInsertId();
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') { // duplicate email created between the check and the insert
                $errors['email'] = 'An account with this email already exists. Try logging in instead.';
            } else {
                throw $ex;
            }
        }
        if (!$errors) {
            login_user(['id' => $userId]);
            flash('success', 'Welcome, ' . $name . '! Your account is ready. Add your first product below.');
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'Create account';
$activeNav = 'register';
include ROOT_PATH . '/includes/header.php';
?>
<section class="container page-narrow section">
    <h1>Create your account</h1>
    <p class="muted">Registered users can add, edit and delete their own products.</p>

    <form class="form" method="post" action="<?= e(url('register.php')) ?>" data-validate="register" novalidate>
        <?= csrf_field() ?>

        <div class="<?= field_class($errors, 'name') ?>">
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" autocomplete="name" maxlength="60" value="<?= e($old['name']) ?>" required>
            <?= field_error($errors, 'name') ?>
        </div>

        <div class="<?= field_class($errors, 'email') ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="190" value="<?= e($old['email']) ?>" required>
            <?= field_error($errors, 'email') ?>
        </div>

        <div class="<?= field_class($errors, 'password') ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="password-hint">
            <p class="field__hint" id="password-hint">At least 8 characters, with a letter and a number.</p>
            <div class="strength" data-strength hidden><span class="strength__bar"></span><span class="strength__text"></span></div>
            <?= field_error($errors, 'password') ?>
        </div>

        <div class="<?= field_class($errors, 'password_confirm') ?>">
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" maxlength="72" required>
            <?= field_error($errors, 'password_confirm') ?>
        </div>

        <button class="btn btn--block" type="submit">Create account</button>
    </form>

    <p class="form-alt">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
