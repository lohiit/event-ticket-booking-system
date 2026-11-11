<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Use POST to release a seat.'], 405);
}

$user = currentUser();
if ($user === null) {
    respond(['error' => 'Log in before managing seat holds.'], 401);
}

if (!hasValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    respond(['error' => 'Your session expired. Refresh the page and try again.'], 403);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['error' => 'Send a valid seat request.'], 400);
}
$showId = filter_var($input['show_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$seatId = filter_var($input['seat_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($showId === false || $seatId === false) {
    respond(['error' => 'Choose a valid show and seat.'], 400);
}

try {
    $statement = db()->prepare('CALL sp_release_seat(:user_id, :show_id, :seat_id)');
    $statement->execute([
        'user_id' => $user['user_id'],
        'show_id' => $showId,
        'seat_id' => $seatId,
    ]);
    $release = $statement->fetch();
    $statement->closeCursor();
    respond(['released' => $release !== false]);
} catch (PDOException $exception) {
    error_log('Seat release failed: ' . $exception->getMessage());
    respond(['error' => 'The seat hold could not be released. Refresh and try again.'], 409);
} catch (Throwable $exception) {
    error_log('Seat release failed: ' . $exception->getMessage());
    respond(['error' => 'Seat selection is temporarily unavailable. Try again.'], 500);
}
