<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/view.php';

$pageTitle = 'Login';
$next = localRedirectPath($_POST['next'] ?? $_GET['next'] ?? '/index.php');
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(formString('email', 254));
    $password = $_POST['password'] ?? '';
    if (!hasValidCsrfToken(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!isValidEmailAddress($email) || !is_string($password)) {
        $error = 'Enter a valid email and password.';
    } else {
        try {
            $statement = db()->prepare('SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                header('Location: ' . $next, true, 303);
                exit;
            }
            $error = 'Email or password is incorrect.';
        } catch (Throwable $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            $error = 'Login is temporarily unavailable. Try again in a moment.';
        }
    }
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="auth-section">
    <form class="auth-form" method="post" action="/login.php">
        <p class="eyebrow">WELCOME BACK</p>
        <h1>Login</h1>
        <?php if ($error !== ''): ?><p class="form-message form-message--error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= e($email) ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn" type="submit">Login</button>
        <p class="auth-switch">New here? <a href="/register.php">Create an account</a></p>
    </form>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
