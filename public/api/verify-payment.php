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

function refundCapturedPayment(string $paymentId, int $amountPaise, string $reason): bool
{
    try {
        $refund = razorpayRequest('POST', '/payments/' . rawurlencode($paymentId) . '/refund', [
            'amount' => $amountPaise,
            'notes' => ['reason' => $reason],
        ]);
        return isset($refund['id'], $refund['payment_id']) && $refund['payment_id'] === $paymentId;
    } catch (Throwable $exception) {
        error_log('Unable to refund captured payment ' . $paymentId . ': ' . $exception->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Use POST to verify a payment.'], 405);
}

$user = currentUser();
if ($user === null) {
    respond(['error' => 'Log in before confirming a payment.'], 401);
}
if (!hasValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    respond(['error' => 'Your session expired. Refresh and try again.'], 403);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['error' => 'Send a valid payment response.'], 400);
}
$orderId = is_string($input['razorpay_order_id'] ?? null) ? trim($input['razorpay_order_id']) : '';
$paymentId = is_string($input['razorpay_payment_id'] ?? null) ? trim($input['razorpay_payment_id']) : '';
$signature = is_string($input['razorpay_signature'] ?? null) ? trim($input['razorpay_signature']) : '';
if ($orderId === '' || $paymentId === '' || $signature === '') {
    respond(['error' => 'The payment response is incomplete.'], 400);
}

startAppSession();
$savedOrder = $_SESSION['razorpay_orders'][$orderId] ?? null;
if (!is_array($savedOrder) || (int) ($savedOrder['user_id'] ?? 0) !== (int) $user['user_id']) {
    respond(['error' => 'This payment order does not belong to your session. Start checkout again.'], 409);
}
if ((int) ($savedOrder['created_at'] ?? 0) < time() - 86400) {
    unset($_SESSION['razorpay_orders'][$orderId]);
    respond(['error' => 'This payment order has expired. Start checkout again.'], 409);
}

try {
    $keys = razorpayKeys();
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keys['secret']);
    if (!hash_equals($expectedSignature, $signature)) {
        respond(['error' => 'Payment verification failed. No booking was confirmed.'], 400);
    }

    $payment = razorpayRequest('GET', '/payments/' . rawurlencode($paymentId));
    $expectedAmount = (int) $savedOrder['amount_paise'];
    if (($payment['id'] ?? null) !== $paymentId
        || ($payment['order_id'] ?? null) !== $orderId
        || ($payment['currency'] ?? null) !== 'INR'
        || (int) ($payment['amount'] ?? 0) !== $expectedAmount) {
        error_log('Razorpay payment identity or amount did not match the server-created order.');
        respond(['error' => 'Payment details did not match your order. Contact support before retrying.'], 409);
    }
    if (($payment['status'] ?? null) !== 'captured' || ($payment['captured'] ?? false) !== true) {
        respond(['error' => 'Razorpay has not captured this payment yet. Check the payment status and try again.'], 409);
    }

    $pdo = db();
    $statement = $pdo->prepare(
        "SELECT b.booking_id, p.provider_reference
         FROM payments p JOIN bookings b ON b.booking_id = p.booking_id
         WHERE p.payment_provider = 'razorpay' AND p.provider_order_id = :order_id
           AND b.user_id = :user_id LIMIT 1"
    );
    $statement->execute(['order_id' => $orderId, 'user_id' => $user['user_id']]);
    $existingBooking = $statement->fetch();
    if ($existingBooking) {
        if ($existingBooking['provider_reference'] === $paymentId) {
            respond(['booking_id' => (int) $existingBooking['booking_id']]);
        }
        if (refundCapturedPayment($paymentId, $expectedAmount, 'duplicate_payment')) {
            respond(['error' => 'A booking already exists for this order. The extra captured payment was refunded.'], 409);
        }
        respond(['error' => 'A booking already exists, but the extra payment refund needs support. Payment ID: ' . $paymentId], 502);
    }

    $statement = $pdo->prepare(
        "SELECT COUNT(*) AS seat_count, CAST(COALESCE(SUM(ss.price), 0.00) AS CHAR) AS total_amount
         FROM seat_holds h JOIN show_seats ss ON ss.show_seat_id = h.show_seat_id
         WHERE h.user_id = :user_id AND ss.show_id = :show_id
           AND h.hold_status = 'active' AND h.expires_at > UTC_TIMESTAMP()
           AND ss.seat_status = 'held'"
    );
    $statement->execute(['user_id' => $user['user_id'], 'show_id' => $savedOrder['show_id']]);
    $totals = $statement->fetch();
    $currentPaise = $totals && (int) $totals['seat_count'] > 0
        ? rupeesToPaise((string) $totals['total_amount'])
        : 0;

    if ($currentPaise !== $expectedAmount) {
        $refunded = refundCapturedPayment($paymentId, $expectedAmount, 'seat_holds_expired_or_changed');
        if ($refunded) {
            unset($_SESSION['razorpay_orders'][$orderId]);
            respond(['error' => 'Your seat hold expired before confirmation. A full refund has been initiated. Please choose seats again.'], 409);
        }
        respond(['error' => 'Payment was captured but your seat hold expired, and the refund request failed. Contact support with payment ID: ' . $paymentId], 502);
    }

    try {
        $statement = $pdo->prepare('CALL sp_confirm_booking(:user_id, :show_id, :order_id, :payment_id)');
        $statement->execute([
            'user_id' => $user['user_id'],
            'show_id' => $savedOrder['show_id'],
            'order_id' => $orderId,
            'payment_id' => $paymentId,
        ]);
        $booking = $statement->fetch();
        $statement->closeCursor();
        if (!$booking) {
            throw new RuntimeException('Booking procedure returned no booking.');
        }

        $bookingTotalPaise = rupeesToPaise((string) $booking['total_amount']);
        if ($bookingTotalPaise !== $expectedAmount) {
            $cancel = $pdo->prepare('CALL sp_cancel_booking(:user_id, :booking_id)');
            $cancel->execute(['user_id' => $user['user_id'], 'booking_id' => $booking['booking_id']]);
            $cancel->closeCursor();
            $refunded = refundCapturedPayment($paymentId, $expectedAmount, 'booking_total_mismatch');
            if ($refunded) {
                unset($_SESSION['razorpay_orders'][$orderId]);
                respond(['error' => 'The confirmed booking total did not match the payment. The seats were released and a refund was initiated.'], 409);
            }
            respond(['error' => 'The booking was cancelled because totals did not match, but the refund needs support. Payment ID: ' . $paymentId], 502);
        }

        unset($_SESSION['razorpay_orders'][$orderId]);
        respond(['booking_id' => (int) $booking['booking_id']]);
    } catch (Throwable $exception) {
        error_log('Booking confirmation failed after captured payment ' . $paymentId . ': ' . $exception->getMessage());
        $refunded = refundCapturedPayment($paymentId, $expectedAmount, 'booking_confirmation_failed');
        if ($refunded) {
            unset($_SESSION['razorpay_orders'][$orderId]);
            respond(['error' => 'Payment was captured but booking confirmation failed. A full refund has been initiated.'], 409);
        }
        respond(['error' => 'Payment was captured but booking confirmation failed. Contact support with payment ID: ' . $paymentId], 502);
    }
} catch (Throwable $exception) {
    error_log('Payment verification failed: ' . $exception->getMessage());
    $status = str_contains($exception->getMessage(), 'not configured') || str_contains($exception->getMessage(), 'enabled') ? 503 : 502;
    respond(['error' => $status === 503 ? $exception->getMessage() : 'Payment verification is temporarily unavailable. Try again shortly.'], $status);
}
