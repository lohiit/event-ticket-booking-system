<?php
$pageTitle = $pageTitle ?? 'Event Ticket Booking System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

<header class="site-header">
    <nav class="navbar">
        <a href="/index.php" class="logo">EventBook</a>

        <div class="nav-links">
            <a href="/events.php">Events</a>
            <a href="/login.php">Login</a>
            <a href="/register.php">Register</a>
        </div>
    </nav>
</header>

<main class="container">
