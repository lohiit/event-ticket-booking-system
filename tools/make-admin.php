<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/config/database.php';
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/make-admin.php registered-account@example.com\n");
    exit(2);
}
fwrite(STDOUT, "Grant administrator access to {$email}? Type PROMOTE to continue: ");
if (trim((string) fgets(STDIN)) !== 'PROMOTE') {
    fwrite(STDOUT, "No changes made.\n");
    exit(0);
}
$pdo = db();
$statement = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = :email");
$statement->execute(['email' => strtolower($email)]);
if ($statement->rowCount() === 0) {
    $check = $pdo->prepare('SELECT role FROM users WHERE email = :email');
    $check->execute(['email' => strtolower($email)]);
    if ($check->fetchColumn() === false) {
        fwrite(STDERR, "No account exists for that email. Register it first, then run this command again.\n");
        exit(1);
    }
}
fwrite(STDOUT, "Administrator access is ready. Sign out and back in to refresh the session.\n");
