<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/view.php';

$pageTitle = 'Browse Events';
$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$events = [];
$categories = [];
$databaseUnavailable = false;

try {
    $pdo = db();
    $categories = $pdo->query("SELECT DISTINCT category FROM events WHERE status = 'published' AND category IS NOT NULL AND category <> '' ORDER BY category")
        ->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT e.event_id, e.title, e.description, e.category, e.image_path, s.show_id, s.starts_at,
                   s.ticket_price, v.name AS venue_name
            FROM events e
            JOIN shows s ON s.event_id = e.event_id
            JOIN venues v ON v.venue_id = s.venue_id
            WHERE e.status = 'published'
              AND s.status = 'scheduled'
              AND s.starts_at >= UTC_TIMESTAMP()";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (e.title LIKE :title_search OR e.description LIKE :description_search)';
        $params['title_search'] = '%' . $search . '%';
        $params['description_search'] = '%' . $search . '%';
    }
    if ($category !== '') {
        $sql .= ' AND e.category = :category';
        $params['category'] = $category;
    }
    $sql .= ' ORDER BY s.starts_at, e.title';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $events = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Unable to load events: ' . $exception->getMessage());
    $databaseUnavailable = true;
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="featured-events">
    <div class="section-heading">
        <p class="eyebrow">EVENT TICKET BOOKING</p>
        <h1>Browse Events</h1>
        <p>Find an event and choose a show to see its details.</p>
    </div>

    <form class="event-search" action="/events.php" method="get">
        <div class="search-field">
            <label for="search">Search events</label>
            <input type="search" id="search" name="search" value="<?= e($search) ?>" placeholder="Search by event name...">
        </div>
        <div class="search-field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $option): ?>
                    <option value="<?= e((string) $option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Search Events</button>
    </form>

    <div class="event-grid">
        <?php if ($databaseUnavailable): ?>
            <div class="empty-state">
                <h2>Events are temporarily unavailable</h2>
                <p>Check the local database setup and try again.</p>
            </div>
        <?php elseif ($events === []): ?>
            <div class="empty-state">
                <h2>No upcoming events found</h2>
                <p>Try another search or check back for new events.</p>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <?php [$artworkPath, $artworkAlt] = eventArtwork((string) $event['title'], $event['image_path'] ?? null); ?>
                <article class="event-card">
                    <div class="event-card-image"><img src="<?= e($artworkPath) ?>" alt="<?= e($artworkAlt) ?>" loading="lazy"><span><?= e($event['category'] ?: 'EVENT') ?></span></div>
                    <div class="event-card-content">
                        <span class="event-category"><?= e($event['category']) ?></span>
                        <h2><?= e($event['title']) ?></h2>
                        <p class="event-meta"><?= e($event['venue_name']) ?> · <?= e(formatIstDateTime($event['starts_at'], 'd M Y, g:i A')) ?></p>
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
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
