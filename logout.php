<?php
require __DIR__ . '/includes/bootstrap.php';

// Logging out changes state, so it only works as a POST request with a valid CSRF token.
if (!is_post()) {
    redirect('index.php');
}
csrf_check();

end_session();
flash('success', 'You have been logged out.');
redirect('index.php');
