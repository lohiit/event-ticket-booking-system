<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/view.php';
requireAdmin();
$pageTitle = 'Admin dashboard';
$counts = db()->query("SELECT (SELECT COUNT(*) FROM events) events, (SELECT COUNT(*) FROM venues) venues, (SELECT COUNT(*) FROM shows WHERE starts_at >= UTC_TIMESTAMP()) upcoming_shows, (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed') confirmed_bookings")->fetch();
require_once __DIR__ . '/../../app/includes/header.php';
?>
<section class="featured-events"><div class="section-heading"><p class="eyebrow">MANAGEMENT</p><h1>Admin dashboard</h1><p>Manage the event catalogue and review customer bookings.</p></div>
<div class="event-grid">
<?php foreach ([['Events','events','/admin/events.php'],['Venues','venues','/admin/venues.php'],['Upcoming shows','upcoming_shows','/admin/shows.php'],['Confirmed bookings','confirmed_bookings','/admin/bookings.php']] as [$label,$key,$href]): ?>
<article class="event-card"><div class="event-card-content"><span class="event-category"><?= e($label) ?></span><h2><?= (int)$counts[$key] ?></h2><a class="btn" href="<?= e($href) ?>">Manage <?= e(strtolower($label)) ?></a></div></article>
<?php endforeach; ?></div><p class="form-message">Booking cancellations and refunds are disabled until a refund policy is approved.</p></section>
<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
