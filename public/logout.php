<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hasValidCsrfToken(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    http_response_code(405);
    exit('Method not allowed.');
}

endAppSession();
header('Location: /index.php', true, 303);
exit;
