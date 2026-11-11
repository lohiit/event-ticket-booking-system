<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/view.php';

$showId = filter_input(INPUT_GET, 'show_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($showId === false || $showId === null) {
    http_response_code(400);
    $showId = 0;
}

$user = currentUser();
if ($user === null && $showId > 0) {
    $next = '/seats.php?show_id=' . $showId;
    header('Location: /login.php?next=' . rawurlencode($next), true, 303);
    exit;
}

$show = null;
$seatsBySection = [];
$databaseUnavailable = false;

if ($showId > 0 && $user !== null) {
    try {
        $pdo = db();
        $cleanup = $pdo->query('CALL sp_release_expired_holds()');
        $cleanup->closeCursor();

        $statement = $pdo->prepare(
            "SELECT s.show_id, s.starts_at, s.ticket_price, e.title, v.name AS venue_name
             FROM shows s
             JOIN events e ON e.event_id = s.event_id
             JOIN venues v ON v.venue_id = s.venue_id
             WHERE s.show_id = :show_id AND s.status = 'scheduled'
               AND e.status = 'published' AND s.starts_at >= UTC_TIMESTAMP()"
        );
        $statement->execute(['show_id' => $showId]);
        $show = $statement->fetch() ?: null;

        if ($show !== null) {
            $statement = $pdo->prepare(
                "SELECT s.seat_id, s.section_name, s.row_label, s.seat_number,
                        ss.price, ss.seat_status,
                        EXISTS(SELECT 1 FROM seat_holds h
                               WHERE h.show_seat_id = ss.show_seat_id AND h.user_id = :held_user_id
                                 AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()) AS held_by_me
                 FROM show_seats ss
                 JOIN seats s ON s.seat_id = ss.seat_id
                 WHERE ss.show_id = :show_id
                 ORDER BY s.section_name, s.row_label, CAST(s.seat_number AS UNSIGNED), s.seat_number"
            );
            $statement->execute(['held_user_id' => $user['user_id'], 'show_id' => $showId]);
            foreach ($statement->fetchAll() as $seat) {
                $seatsBySection[(string) $seat['section_name']][(string) $seat['row_label']][] = $seat;
            }
        }
    } catch (Throwable $exception) {
        error_log('Unable to load seat map: ' . $exception->getMessage());
        $databaseUnavailable = true;
    }
}

$pageTitle = $show ? 'Choose Seats · ' . $show['title'] : 'Choose Seats';
require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="seat-selection-section">
    <?php if ($databaseUnavailable): ?>
        <div class="empty-state">
            <h1>Seat selection is temporarily unavailable</h1>
            <p>Refresh the page in a moment.</p>
        </div>
    <?php elseif ($show === null): ?>
        <div class="empty-state">
            <h1>Show not found</h1>
            <p>This show may have started, been cancelled, or no longer be published.</p>
            <a href="/events.php" class="btn">Browse Events</a>
        </div>
    <?php elseif ($seatsBySection === []): ?>
        <div class="empty-state">
            <h1>No seats are configured for this show</h1>
            <p>Ask an administrator to check the venue seat layout.</p>
        </div>
    <?php else: ?>
        <div class="section-heading">
            <p class="eyebrow">CHOOSE YOUR SEATS</p>
            <h1><?= e($show['title']) ?></h1>
            <p><?= e($show['venue_name']) ?> · <?= e(date('l, d M Y · g:i A', strtotime($show['starts_at'] . ' UTC'))) ?></p>
        </div>

        <div id="seat-selector" class="seat-selector" data-show-id="<?= (int) $show['show_id'] ?>" data-csrf="<?= e(csrfToken()) ?>">
            <div class="seat-legend" aria-label="Seat status legend">
                <span><i class="seat-legend-dot seat-legend-dot--available"></i>Available</span>
                <span><i class="seat-legend-dot seat-legend-dot--selected"></i>Your selection</span>
                <span><i class="seat-legend-dot seat-legend-dot--unavailable"></i>Unavailable</span>
            </div>

            <div class="seat-screen">STAGE / SCREEN</div>

            <div class="seat-sections">
                <?php foreach ($seatsBySection as $sectionName => $rows): ?>
                    <section class="seat-section">
                        <h2><?= e($sectionName) ?></h2>
                        <?php foreach ($rows as $rowLabel => $rowSeats): ?>
                            <div class="seat-row" aria-label="Row <?= e($rowLabel) ?>">
                                <span class="seat-row-label"><?= e($rowLabel) ?></span>
                                <?php foreach ($rowSeats as $seat): ?>
                                    <?php
                                    $heldByMe = (bool) $seat['held_by_me'];
                                    $available = $seat['seat_status'] === 'available' || $heldByMe;
                                    $seatClass = $heldByMe ? 'seat--selected' : ($available ? 'seat--available' : 'seat--unavailable');
                                    $seatLabel = $seat['row_label'] . $seat['seat_number'];
                                    ?>
                                    <button
                                        type="button"
                                        class="seat <?= $seatClass ?>"
                                        data-seat-id="<?= (int) $seat['seat_id'] ?>"
                                        data-price="<?= e((string) $seat['price']) ?>"
                                        data-seat-label="<?= e($seatLabel) ?>"
                                        data-held="<?= $heldByMe ? '1' : '0' ?>"
                                        aria-pressed="<?= $heldByMe ? 'true' : 'false' ?>"
                                        aria-label="Seat <?= e($seatLabel) ?>, <?= $heldByMe ? 'held by you' : ($available ? 'available' : 'unavailable') ?>"
                                        <?= $available ? '' : 'disabled' ?>
                                    ><?= e((string) $seat['seat_number']) ?></button>
                                <?php endforeach; ?>
                                <span class="seat-row-label"><?= e($rowLabel) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <aside class="seat-summary">
                <div>
                    <strong id="selected-seat-count">0</strong> seats selected
                    <p id="selected-seat-labels">Choose available seats above.</p>
                </div>
                <div class="seat-summary-total">
                    <span>Total</span>
                    <strong id="selected-seat-total">₹0.00</strong>
                </div>
                <a id="continue-to-checkout" class="btn seat-continue" href="/checkout.php?show_id=<?= (int) $show['show_id'] ?>" aria-disabled="true">Continue to Checkout</a>
            </aside>
            <p id="seat-message" class="form-message" role="status" aria-live="polite"></p>
        </div>
        <script src="/assets/js/seat-selection.js" defer></script>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
