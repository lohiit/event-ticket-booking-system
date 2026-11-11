<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/view.php';

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$user = currentUser();
if ($user === null) {
    header('Location: /login.php?next=' . rawurlencode('/my-bookings.php'), true, 303);
    exit;
}

$booking = null;
$databaseUnavailable = false;
if ($bookingId !== false && $bookingId !== null) {
    try {
        $statement = db()->prepare(
            "SELECT b.booking_id, b.status, b.total_amount, b.created_at,
                    e.title, s.starts_at, v.name AS venue_name,
                    p.payment_status, p.provider_reference
             FROM bookings b
             JOIN shows s ON s.show_id = b.show_id
             JOIN events e ON e.event_id = s.event_id
             JOIN venues v ON v.venue_id = s.venue_id
             LEFT JOIN payments p ON p.booking_id = b.booking_id
             WHERE b.booking_id = :booking_id AND b.user_id = :user_id LIMIT 1"
        );
        $statement->execute(['booking_id' => $bookingId, 'user_id' => $user['user_id']]);
        $booking = $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        error_log('Unable to load booking receipt: ' . $exception->getMessage());
        $databaseUnavailable = true;
    }
}

$pageTitle = 'Booking Receipt';
require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="featured-events">
    <?php if ($databaseUnavailable): ?>
        <div class="empty-state"><h1>Receipt is temporarily unavailable</h1><p>Open My bookings to try again.</p></div>
    <?php elseif ($booking === null): ?>
        <div class="empty-state"><h1>Booking not found</h1><p>This receipt is unavailable for your account.</p><a href="/my-bookings.php" class="btn">My Bookings</a></div>
    <?php elseif ($booking['status'] !== 'confirmed'): ?>
        <div class="empty-state"><h1>This booking was cancelled</h1><p>Booking #<?= (int) $booking['booking_id'] ?></p><a href="/my-bookings.php" class="btn">My Bookings</a></div>
    <?php else: ?>
        <div class="section-heading">
            <p class="eyebrow">BOOKING CONFIRMED</p>
            <h1><?= e($booking['title']) ?></h1>
            <p>Booking #<?= (int) $booking['booking_id'] ?> · <?= e(date('d M Y, g:i A', strtotime($booking['created_at'] . ' UTC'))) ?></p>
        </div>
        <div class="checkout-summary">
            <h2>Event details</h2>
            <p><?= e($booking['venue_name']) ?> · <?= e(date('l, d M Y · g:i A', strtotime($booking['starts_at'] . ' UTC'))) ?></p>
            <p class="checkout-total"><span>Paid</span><strong>₹<?= e(number_format((float) $booking['total_amount'], 2)) ?> INR</strong></p>
            <p>Payment status: <?= e((string) ($booking['payment_status'] ?? 'pending')) ?></p>
            <?php if (!empty($booking['provider_reference'])): ?><p>Payment ID: <?= e($booking['provider_reference']) ?></p><?php endif; ?>
            <a href="/my-bookings.php" class="btn">My Bookings</a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
