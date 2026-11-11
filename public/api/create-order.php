<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/razorpay.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Use POST to start checkout.'], 405);
}

$user = currentUser();
if ($user === null) {
    respond(['error' => 'Log in before checkout.'], 401);
}
if (!hasValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    respond(['error' => 'Your session expired. Refresh and try again.'], 403);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['error' => 'Send a valid checkout request.'], 400);
}
$showId = filter_var($input['show_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($showId === false) {
    respond(['error' => 'Choose a valid show.'], 400);
}

try {
    $statement = db()->prepare(
        "SELECT COUNT(*) AS seat_count, CAST(COALESCE(SUM(ss.price), 0.00) AS CHAR) AS total_amount
         FROM seat_holds h
         JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
         WHERE h.user_id = :user_id AND ss.show_id = :show_id
           AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
           AND ss.seat_status = 'held'"
    );
    $statement->execute(['user_id' => $user['user_id'], 'show_id' => $showId]);
    $totals = $statement->fetch();
    if (!$totals || (int) $totals['seat_count'] < 1) {
        respond(['error' => 'Your seat holds expired. Choose your seats again.'], 409);
    }

    $amountPaise = rupeesToPaise((string) $totals['total_amount']);
    if ($amountPaise < 100) {
        respond(['error' => 'The minimum checkout amount is ₹1.00.'], 422);
    }

    $receipt = 'evt_' . bin2hex(random_bytes(12));
    $order = razorpayRequest('POST', '/orders', [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'notes' => [
            'user_id' => (string) $user['user_id'],
            'show_id' => (string) $showId,
        ],
    ]);

    if (!isset($order['id'], $order['amount'], $order['currency'])
        || (int) $order['amount'] !== $amountPaise || $order['currency'] !== 'INR') {
        error_log('Razorpay order response did not match the held seat total.');
        respond(['error' => 'The payment order did not match your seat total. Refresh and try again.'], 502);
    }

    startAppSession();
    $_SESSION['razorpay_orders'] ??= [];
    foreach ($_SESSION['razorpay_orders'] as $id => $savedOrder) {
        if (!is_array($savedOrder) || ($savedOrder['created_at'] ?? 0) < time() - 3600) {
            unset($_SESSION['razorpay_orders'][$id]);
        }
    }
    $_SESSION['razorpay_orders'][$order['id']] = [
        'user_id' => (int) $user['user_id'],
        'show_id' => (int) $showId,
        'amount_paise' => $amountPaise,
        'created_at' => time(),
    ];

    $keys = razorpayKeys();
    respond([
        'id' => (string) $order['id'],
        'amount' => $amountPaise,
        'currency' => 'INR',
        'key_id' => $keys['key_id'],
    ]);
} catch (Throwable $exception) {
    error_log('Unable to create Razorpay order: ' . $exception->getMessage());
    $status = str_contains($exception->getMessage(), 'not configured') || str_contains($exception->getMessage(), 'enabled') ? 503 : 502;
    respond(['error' => $status === 503 ? $exception->getMessage() : 'Payment service is temporarily unavailable. Try again shortly.'], $status);
}
