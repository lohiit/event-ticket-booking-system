<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/view.php';

$pageTitle = 'Create Account';
$next = localRedirectPath($_POST['next'] ?? $_GET['next'] ?? '/index.php');
$fullName = '';
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = formString('full_name', 120);
    $email = strtolower(formString('email', 254));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!hasValidCsrfToken(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($fullName === '' || !isValidEmailAddress($email)) {
        $error = 'Enter your name and a valid email address.';
    } elseif (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
        $error = 'Use a password between 8 and 72 characters.';
    } elseif (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $statement = db()->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, 'customer')");
            $statement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            loginUser([
                'user_id' => db()->lastInsertId(),
                'full_name' => $fullName,
                'email' => $email,
                'role' => 'customer',
            ]);
            header('Location: ' . $next, true, 303);
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'An account with that email already exists. Try logging in.';
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                $error = 'Registration is temporarily unavailable. Try again in a moment.';
            }
        } catch (Throwable $exception) {
            error_log('Registration failed: ' . $exception->getMessage());
            $error = 'Registration is temporarily unavailable. Try again in a moment.';
        }
    }
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="auth-section">
    <form class="auth-form" method="post" action="/register.php">
        <p class="eyebrow">JOIN EVENTBOOK</p>
        <h1>Create Account</h1>
        <?php if ($error !== ''): ?><p class="form-message form-message--error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" maxlength="120" autocomplete="name" required value="<?= e($fullName) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= e($email) ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        <button class="btn" type="submit">Create Account</button>
        <p class="auth-switch">Already have an account? <a href="/login.php">Login</a></p>
    </form>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
