<?php

$pageTitle = 'Home';

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="hero">
    <h1>Discover Events</h1>
    <p>Find events, choose your seats, and book your tickets.</p>

    <a href="/events.php">Browse Events</a>
</section>

<section class="featured-events">
    <h2>Featured Events</h2>

    <p>Events will be loaded from the database here.</p>
</section>

<?php
require_once __DIR__ . '/../app/includes/footer.php';
?>
