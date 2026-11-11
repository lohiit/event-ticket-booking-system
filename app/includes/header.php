<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/view.php';

$pageTitle = $pageTitle ?? 'Event Ticket Booking System';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

<header class="site-header">
    <nav class="navbar">
        <a href="/index.php" class="logo" aria-label="EventBook home"><img src="/assets/images/eventbook-logo.svg" alt="EventBook"></a>

        <div class="nav-links">
            <a href="/events.php">Events</a>
            <?php if ($user !== null): ?>
                <a href="/my-bookings.php">My bookings</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?><a href="/admin/">Admin</a><?php endif; ?>
                <span class="nav-user">Hi, <?= e($user['full_name']) ?></span>
                <form class="nav-logout" action="/logout.php" method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="/login.php">Login</a>
                <a href="/register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container">
