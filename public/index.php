<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/view.php';

$pageTitle = 'Home';
$bodyClass = 'home-page';
$featuredEvents = [];
$categories = [];
$databaseUnavailable = false;

try {
    $pdo = db();
    $featuredEvents = $pdo->query(
        "SELECT e.event_id, e.title, e.description, e.category, e.image_path, s.starts_at,
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

<section class="hero hero--editorial">
    <div class="hero-inner">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-mark"></span> GOOD THINGS HAPPEN LIVE</p>
            <h1>Make room for<br><em>a night to</em><br>remember.</h1>
            <p class="hero-description">From first notes to final bows, find the moments worth going out for.</p>
            <div class="hero-actions">
                <a href="/events.php" class="hero-primary-link">Explore events <span aria-hidden="true">↗</span></a>
                <a href="#featured" class="hero-secondary-link">See what’s on</a>
            </div>
            <div class="hero-footnote"><span class="hero-footnote-rule"></span><span><?= count($featuredEvents) ?> upcoming experiences</span><span class="hero-footnote-dot">·</span><span>Choose your seats</span></div>
        </div>

        <?php if ($featuredEvents !== []): ?>
            <?php $heroEvent = $featuredEvents[0]; [$heroArtwork, $heroArtworkAlt] = eventArtwork((string) $heroEvent['title'], $heroEvent['image_path'] ?? null); ?>
            <a class="hero-feature" href="/event-details.php?event_id=<?= (int) $heroEvent['event_id'] ?>" aria-label="View <?= e($heroEvent['title']) ?>">
                <img src="<?= e($heroArtwork) ?>" alt="<?= e($heroArtworkAlt) ?>">
                <span class="hero-feature-wash"></span>
                <span class="hero-feature-top"><span>UP NEXT</span><span><?= e(strtoupper((string) ($heroEvent['category'] ?: 'EVENT'))) ?></span></span>
                <span class="hero-feature-caption"><span class="hero-feature-date"><?= e(formatIstDateTime($heroEvent['starts_at'], 'd M · g:i A')) ?></span><strong><?= e($heroEvent['title']) ?></strong><span><?= e($heroEvent['venue_name']) ?> · Mumbai</span></span>
                <span class="hero-feature-arrow" aria-hidden="true">↗</span>
            </a>
        <?php else: ?>
            <div class="hero-feature hero-feature--empty"><img src="/assets/images/event-default.svg" alt=""><span class="hero-feature-caption"><strong>Your next night out starts here.</strong><span>New events are on the way.</span></span></div>
        <?php endif; ?>

        <form class="home-search" action="/events.php" method="get">
            <div class="home-search-intro"><span class="home-search-icon" aria-hidden="true">⌕</span><div><strong>Find your event</strong><span>Make a plan worth leaving home for.</span></div></div>
            <label class="home-search-field"><span>EVENT NAME</span><input type="search" id="search" name="search" placeholder="Try live music, theatre…"></label>
            <label class="home-search-field home-search-category"><span>WHAT ARE YOU INTO?</span><select id="category" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category) ?>"><?= e((string) $category) ?></option><?php endforeach; ?></select></label>
            <button type="submit" class="home-search-submit">Find tickets <span aria-hidden="true">→</span></button>
        </form>
    </div>
</section>

<section class="featured-events home-featured" id="featured">
    <div class="container home-featured-container">
        <div class="section-heading">
            <div><p class="eyebrow">A FEW GOOD PLANS</p><h2>On the calendar</h2><p>Pick a date. We’ll save you a seat.</p></div>
            <a class="home-view-all" href="/events.php">All events <span aria-hidden="true">↗</span></a>
        </div>

        <div class="event-grid home-event-grid">
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
                    <?php [$artworkPath, $artworkAlt] = eventArtwork((string) $event['title'], $event['image_path'] ?? null); ?>
                    <article class="event-card">
                        <div class="event-card-image"><img src="<?= e($artworkPath) ?>" alt="<?= e($artworkAlt) ?>" loading="lazy"><span><?= e($event['category'] ?: 'EVENT') ?></span></div>
                        <div class="event-card-content">
                            <span class="event-category"><?= e($event['category']) ?></span>
                            <h3><?= e($event['title']) ?></h3>
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
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
