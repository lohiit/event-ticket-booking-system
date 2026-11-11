<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/view.php';

$pageTitle = 'Home';
$featuredEvents = [];
$categories = [];
$databaseUnavailable = false;

try {
    $pdo = db();
    $featuredEvents = $pdo->query(
        "SELECT e.event_id, e.title, e.description, e.category, s.starts_at,
                s.ticket_price, v.name AS venue_name
         FROM events e
         JOIN shows s ON s.event_id = e.event_id
         JOIN venues v ON v.venue_id = s.venue_id
         WHERE e.status = 'published' AND s.status = 'scheduled'
           AND s.starts_at >= UTC_TIMESTAMP()
         ORDER BY s.starts_at, e.title
         LIMIT 3"
    )->fetchAll();
    $categories = $pdo->query(
        "SELECT DISTINCT category FROM events
         WHERE status = 'published' AND category IS NOT NULL AND category <> ''
         ORDER BY category"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    error_log('Unable to load homepage events: ' . $exception->getMessage());
    $databaseUnavailable = true;
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">EVENT TICKET BOOKING</p>
        <h1>Discover Events</h1>
        <p class="hero-description">Find events, choose your seats, and book your tickets with ease.</p>

        <form class="event-search" action="/events.php" method="get">
            <div class="search-field">
                <label for="search">Search events</label>
                <input type="search" id="search" name="search" placeholder="Search by event name...">
            </div>
            <div class="search-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category) ?>"><?= e((string) $category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Search Events</button>
        </form>
        <a href="/events.php" class="hero-link">Browse all events</a>
    </div>
</section>

<section class="featured-events">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">WHAT'S HAPPENING</p>
            <h2>Featured Events</h2>
            <p>Upcoming events and show times from our current listings.</p>
        </div>

        <div class="event-grid">
            <?php if ($databaseUnavailable): ?>
                <div class="empty-state">
                    <h3>Events are temporarily unavailable</h3>
                    <p>Check the local database setup and try again.</p>
                </div>
            <?php elseif ($featuredEvents === []): ?>
                <div class="empty-state">
                    <h3>No upcoming events yet</h3>
                    <p>Check back soon for new listings.</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredEvents as $event): ?>
                    <?php [$artworkPath, $artworkAlt] = eventArtwork((string) $event['title']); ?>
                    <article class="event-card">
                        <div class="event-card-image"><img src="<?= e($artworkPath) ?>" alt="<?= e($artworkAlt) ?>" loading="lazy"><span><?= e($event['category'] ?: 'EVENT') ?></span></div>
                        <div class="event-card-content">
                            <span class="event-category"><?= e($event['category']) ?></span>
                            <h3><?= e($event['title']) ?></h3>
                            <p class="event-meta"><?= e($event['venue_name']) ?> · <?= e(date('d M Y, g:i A', strtotime($event['starts_at'] . ' UTC'))) ?></p>
                            <p class="event-description"><?= e($event['description']) ?></p>
                            <div class="event-card-footer">
                                <span class="event-price">From ₹<?= e(number_format((float) $event['ticket_price'], 2)) ?></span>
                                <a href="/event-details.php?event_id=<?= (int) $event['event_id'] ?>" class="btn">View Details</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
