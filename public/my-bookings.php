<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/view.php';

$user = currentUser();
if ($user === null) {
    header('Location: /login.php?next=' . rawurlencode('/my-bookings.php'), true, 303);
    exit;
}

$bookings = [];
$databaseUnavailable = false;
try {
    $statement = db()->prepare(
        "SELECT b.booking_id, b.status, b.total_amount, b.created_at,
                e.title, s.starts_at, v.name AS venue_name,
                p.payment_status,
                GROUP_CONCAT(CONCAT(seats.section_name, ' ', seats.row_label, seats.seat_number)
                             ORDER BY seats.section_name, seats.row_label, seats.seat_number SEPARATOR ', ') AS seat_labels
         FROM bookings b
         JOIN shows s ON s.show_id = b.show_id
         JOIN events e ON e.event_id = s.event_id
         JOIN venues v ON v.venue_id = s.venue_id
         LEFT JOIN payments p ON p.booking_id = b.booking_id
         LEFT JOIN booking_seats bs ON bs.booking_id = b.booking_id
         LEFT JOIN show_seats ss ON ss.show_seat_id = bs.show_seat_id
         LEFT JOIN seats ON seats.seat_id = ss.seat_id
         WHERE b.user_id = :user_id
         GROUP BY b.booking_id, p.payment_id
         ORDER BY b.created_at DESC"
    );
    $statement->execute(['user_id' => $user['user_id']]);
    $bookings = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Unable to load user bookings: ' . $exception->getMessage());
    $databaseUnavailable = true;
}

$pageTitle = 'My Bookings';
require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="featured-events">
    <div class="section-heading"><p class="eyebrow">YOUR ACCOUNT</p><h1>My Bookings</h1></div>
    <div class="event-grid">
        <?php if ($databaseUnavailable): ?>
            <div class="empty-state"><h2>Bookings are temporarily unavailable</h2><p>Refresh the page in a moment.</p></div>
        <?php elseif ($bookings === []): ?>
            <div class="empty-state"><h2>No bookings yet</h2><p>Choose an upcoming event to book your first seats.</p><a href="/events.php" class="btn">Browse Events</a></div>
        <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
                <article class="event-card"><div class="event-card-content">
                    <span class="event-category"><?= e($booking['status']) ?></span>
                    <h2><?= e($booking['title']) ?></h2>
                    <p class="event-meta"><?= e($booking['venue_name']) ?> · <?= e(formatIstDateTime($booking['starts_at'], 'd M Y, g:i A')) ?></p>
                    <p class="event-description">Seats: <?= e($booking['seat_labels'] ?: '—') ?><br>Payment: <?= e((string) ($booking['payment_status'] ?? 'pending')) ?></p>
                    <div class="event-card-footer"><span class="event-price">₹<?= e(number_format((float) $booking['total_amount'], 2)) ?></span><a href="/booking-success.php?booking_id=<?= (int) $booking['booking_id'] ?>" class="btn">View Receipt</a></div>
                </div></article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
