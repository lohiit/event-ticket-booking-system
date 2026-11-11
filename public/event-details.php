<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/view.php';

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$event = null;
$shows = [];
$databaseUnavailable = false;

if ($eventId !== false && $eventId !== null) {
    try {
        $pdo = db();
        $statement = $pdo->prepare("SELECT event_id, title, description, category FROM events WHERE event_id = :event_id AND status = 'published'");
        $statement->execute(['event_id' => $eventId]);
        $event = $statement->fetch() ?: null;

        if ($event !== null) {
            $statement = $pdo->prepare(
                "SELECT s.show_id, s.starts_at, s.ends_at, s.ticket_price, v.name AS venue_name,
                        v.address AS venue_address,
                        (SELECT COUNT(*) FROM show_seats ss
                         WHERE ss.show_id = s.show_id AND ss.seat_status = 'available') AS available_seats
                 FROM shows s
                 JOIN venues v ON v.venue_id = s.venue_id
                 WHERE s.event_id = :event_id AND s.status = 'scheduled'
                   AND s.starts_at >= UTC_TIMESTAMP()
                 ORDER BY s.starts_at"
            );
            $statement->execute(['event_id' => $eventId]);
            $shows = $statement->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log('Unable to load event details: ' . $exception->getMessage());
        $databaseUnavailable = true;
    }
}

$pageTitle = $event ? (string) $event['title'] : 'Event Details';
require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="featured-events">
    <?php if ($databaseUnavailable): ?>
        <div class="empty-state">
            <h1>Event details are temporarily unavailable</h1>
            <p>Check the local database setup and try again.</p>
            <a href="/events.php" class="btn">Back to Events</a>
        </div>
    <?php elseif ($event === null): ?>
        <div class="empty-state">
            <h1>Event not found</h1>
            <p>This event may have been removed or is no longer published.</p>
            <a href="/events.php" class="btn">Browse Events</a>
        </div>
    <?php else: ?>
        <?php [$eventArtworkPath, $eventArtworkAlt] = eventArtwork((string) $event['title']); ?>
        <div class="event-detail-art"><img src="<?= e($eventArtworkPath) ?>" alt="<?= e($eventArtworkAlt) ?>"></div>
        <div class="section-heading">
            <p class="eyebrow"><?= e($event['category']) ?></p>
            <h1><?= e($event['title']) ?></h1>
            <p><?= e($event['description']) ?></p>
        </div>

        <h2>Upcoming Shows</h2>
        <div class="event-grid">
            <?php if ($shows === []): ?>
                <div class="empty-state">
                    <h3>No upcoming shows</h3>
                    <p>Please check back for new dates.</p>
                </div>
            <?php else: ?>
                <?php foreach ($shows as $show): ?>
                    <article class="event-card">
                        <div class="event-card-content">
                            <h3><?= e(date('l, d M Y · g:i A', strtotime($show['starts_at'] . ' UTC'))) ?></h3>
                            <p class="event-meta"><?= e($show['venue_name']) ?><?= $show['venue_address'] ? ' · ' . e($show['venue_address']) : '' ?></p>
                            <p class="event-description">
                                Ticket price: ₹<?= e(number_format((float) $show['ticket_price'], 2)) ?><br>
                                Available seats: <?= (int) $show['available_seats'] ?>
                            </p>
                            <div class="event-card-footer">
                                <span class="event-price">₹<?= e(number_format((float) $show['ticket_price'], 2)) ?></span>
                                <?php if ((int) $show['available_seats'] > 0): ?>
                                    <a href="/seats.php?show_id=<?= (int) $show['show_id'] ?>" class="btn">Choose Seats</a>
                                <?php else: ?>
                                    <span>Sold out</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
