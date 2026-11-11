<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/razorpay.php';
require_once __DIR__ . '/../app/helpers/view.php';

$showId = filter_input(INPUT_GET, 'show_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($showId === false || $showId === null) {
    http_response_code(400);
    $showId = 0;
}

$user = currentUser();
if ($user === null && $showId > 0) {
    header('Location: /login.php?next=' . rawurlencode('/checkout.php?show_id=' . $showId), true, 303);
    exit;
}

$show = null;
$heldSeats = [];
$databaseUnavailable = false;

if ($showId > 0 && $user !== null) {
    try {
        $pdo = db();
        $cleanup = $pdo->query('CALL sp_release_expired_holds()');
        $cleanup->closeCursor();

        $statement = $pdo->prepare(
            "SELECT e.title, s.starts_at, v.name AS venue_name,
                    h.hold_id, h.expires_at, seats.section_name, seats.row_label,
                    seats.seat_number, ss.price
             FROM seat_holds h
             JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
             JOIN seats ON seats.seat_id = ss.seat_id
             JOIN shows s ON s.show_id = ss.show_id
             JOIN events e ON e.event_id = s.event_id
             JOIN venues v ON v.venue_id = s.venue_id
             WHERE h.user_id = :user_id AND s.show_id = :show_id
               AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
               AND s.status = 'scheduled' AND e.status = 'published'
             ORDER BY seats.section_name, seats.row_label, seats.seat_number"
        );
        $statement->execute(['user_id' => $user['user_id'], 'show_id' => $showId]);
        $heldSeats = $statement->fetchAll();
        if ($heldSeats !== []) {
            $show = [
                'title' => $heldSeats[0]['title'],
                'starts_at' => $heldSeats[0]['starts_at'],
                'venue_name' => $heldSeats[0]['venue_name'],
                'expires_at' => $heldSeats[0]['expires_at'],
            ];
        }
    } catch (Throwable $exception) {
        error_log('Unable to load checkout summary: ' . $exception->getMessage());
        $databaseUnavailable = true;
    }
}

$pageTitle = 'Checkout';
$razorpayKeyId = '';
$paymentConfigured = false;
$paymentButtonLabel = 'Pay with Razorpay Test Mode';
$paymentSetupMessage = 'Add Razorpay Test Mode keys to your local .env file to enable the payment button.';
try {
    $razorpayKeyId = razorpayKeys()['key_id'];
    $paymentConfigured = true;
    if (strtolower((string) (getenv('APP_ENV') ?: 'local')) === 'production') {
        $paymentButtonLabel = 'Pay securely';
    }
} catch (Throwable $exception) {
    if (str_contains($exception->getMessage(), 'cURL')) {
        $paymentSetupMessage = 'Enable the PHP cURL extension and add Razorpay Test Mode keys to your local .env file.';
    }
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="featured-events">
    <?php if ($databaseUnavailable): ?>
        <div class="empty-state"><h1>Checkout is temporarily unavailable</h1><p>Refresh the page in a moment.</p></div>
    <?php elseif ($heldSeats === []): ?>
        <div class="empty-state">
            <h1>No active seat holds</h1>
            <p>Seat holds last five minutes. Choose your seats again to continue.</p>
            <?php if ($showId > 0): ?><a href="/seats.php?show_id=<?= (int) $showId ?>" class="btn">Choose Seats</a><?php endif; ?>
        </div>
    <?php else: ?>
        <?php $total = array_sum(array_map(static fn (array $seat): float => (float) $seat['price'], $heldSeats)); ?>
        <div class="section-heading">
            <p class="eyebrow">CHECKOUT</p>
            <h1><?= e($show['title']) ?></h1>
            <p><?= e($show['venue_name']) ?> · <?= e(date('l, d M Y · g:i A', strtotime($show['starts_at'] . ' UTC'))) ?></p>
        </div>
        <div class="checkout-summary">
            <h2>Your seats</h2>
            <ul>
                <?php foreach ($heldSeats as $seat): ?>
                    <li>
                        <span><?= e($seat['section_name'] . ' · ' . $seat['row_label'] . $seat['seat_number']) ?></span>
                        <strong>₹<?= e(number_format((float) $seat['price'], 2)) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="checkout-total"><span>Total</span><strong>₹<?= e(number_format($total, 2)) ?></strong></p>
            <p class="form-message">Your seats are held until <?= e(date('g:i A', strtotime($show['expires_at'] . ' UTC'))) ?>. Payment setup is the next step.</p>
            <a href="/seats.php?show_id=<?= (int) $showId ?>" class="btn">Change Seats</a>
            <?php if ($paymentConfigured): ?>
                <button
                    id="pay-with-razorpay"
                    class="btn"
                    type="button"
                    data-key-id="<?= e($razorpayKeyId) ?>"
                    data-show-id="<?= (int) $showId ?>"
                    data-csrf="<?= e(csrfToken()) ?>"
                    data-event-title="<?= e($show['title']) ?>"
                ><?= e($paymentButtonLabel) ?></button>
            <?php else: ?>
                <p class="form-message form-message--error"><?= e($paymentSetupMessage) ?></p>
            <?php endif; ?>
            <p id="payment-message" class="form-message" role="status" aria-live="polite"></p>
        </div>
    <?php endif; ?>
</section>

<?php if ($paymentConfigured && $heldSeats !== []): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    (() => {
        const button = document.getElementById('pay-with-razorpay');
        const message = document.getElementById('payment-message');
        const csrf = button.dataset.csrf;

        function showMessage(text, isError = false) {
            message.textContent = text;
            message.classList.toggle('form-message--error', isError);
        }

        button.addEventListener('click', async () => {
            button.disabled = true;
            showMessage('Creating your secure payment order…');
            try {
                const orderResponse = await fetch('/api/create-order.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ show_id: Number(button.dataset.showId) }),
                });
                const order = await orderResponse.json();
                if (!orderResponse.ok) throw new Error(order.error || 'Could not start payment.');

                const options = {
                    key: order.key_id,
                    amount: order.amount,
                    currency: order.currency,
                    name: 'EventBook',
                    description: button.dataset.eventTitle,
                    order_id: order.id,
                    handler: async (paymentResponse) => {
                        showMessage('Verifying your payment…');
                        try {
                            const verifyResponse = await fetch('/api/verify-payment.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                                body: JSON.stringify(paymentResponse),
                            });
                            const result = await verifyResponse.json();
                            if (!verifyResponse.ok) throw new Error(result.error || 'Payment verification failed.');
                            window.location.assign('/booking-success.php?booking_id=' + encodeURIComponent(result.booking_id));
                        } catch (error) {
                            showMessage(error.message || 'Payment verification failed. Contact support before retrying.', true);
                            button.disabled = false;
                        }
                    },
                    prefill: {
                        name: <?= json_encode((string) $user['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                        email: <?= json_encode((string) $user['email'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    },
                    theme: { color: '#2563eb' },
                    modal: { ondismiss: () => { button.disabled = false; showMessage('Payment window closed. Your seat hold is still active until it expires.'); } },
                };
                const checkout = new Razorpay(options);
                checkout.on('payment.failed', (event) => {
                    const detail = event.error && event.error.description ? event.error.description : 'Payment did not complete.';
                    showMessage(detail + ' You can try again while your seats are held.', true);
                    button.disabled = false;
                });
                checkout.open();
                showMessage(<?= json_encode(strtolower((string) (getenv('APP_ENV') ?: 'local')) === 'production' ? 'Complete payment in the secure payment window.' : 'Complete payment in the Razorpay Test Mode window.') ?>);
            } catch (error) {
                showMessage(error.message || 'Could not start payment. Try again.', true);
                button.disabled = false;
            }
        });
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
