<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function razorpayKeys(): array
{
    loadProjectEnvironment();
    $keyId = trim((string) (getenv('RAZORPAY_KEY_ID') ?: ''));
    $secret = trim((string) (getenv('RAZORPAY_KEY_SECRET') ?: ''));
    $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'local')));

    if ($keyId === '' || $secret === '' || str_contains($keyId, 'replace') || str_contains($secret, 'replace')) {
        throw new RuntimeException('Razorpay Test Mode keys are not configured.');
    }
    $expectedPrefix = match ($environment) {
        'local', 'test' => 'rzp_test_',
        'production' => 'rzp_live_',
        default => throw new RuntimeException('APP_ENV must be local, test, or production.'),
    };
    if (!str_starts_with($keyId, $expectedPrefix)) {
        throw new RuntimeException($environment === 'production'
            ? 'Production requires Razorpay Live Mode keys.'
            : 'Local and test environments require Razorpay Test Mode keys.');
    }
    if (!extension_loaded('curl')) {
        throw new RuntimeException('PHP cURL must be enabled to contact Razorpay.');
    }

    return ['key_id' => $keyId, 'secret' => $secret];
}

function razorpayRequest(string $method, string $path, ?array $payload = null): array
{
    $keys = razorpayKeys();
    $handle = curl_init('https://api.razorpay.com/v1' . $path);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the Razorpay request.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $keys['key_id'] . ':' . $keys['secret'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $caBundle = trim((string) (getenv('CURL_CA_BUNDLE') ?: ''));
    if ($caBundle !== '') {
        if (!is_file($caBundle) || !is_readable($caBundle)) {
            throw new RuntimeException('The configured CA bundle file is unavailable.');
        }
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($handle, $options);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);

    if ($body === false) {
        error_log('Razorpay connection failed: ' . $curlError);
        throw new RuntimeException('Could not reach Razorpay. Check the connection and try again.');
    }

    $response = json_decode($body, true);
    if (!is_array($response)) {
        error_log('Razorpay returned an invalid response (HTTP ' . $status . ').');
        throw new RuntimeException('Razorpay returned an invalid response. Try again.');
    }
    if ($status < 200 || $status >= 300) {
        $providerMessage = $response['error']['description'] ?? 'request rejected';
        error_log('Razorpay request rejected (HTTP ' . $status . '): ' . (string) $providerMessage);
        throw new RuntimeException('Razorpay could not complete the request. Check the Test Mode keys and try again.');
    }

    return $response;
}

function rupeesToPaise(string $amount): int
{
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $parts)) {
        throw new RuntimeException('The ticket total is not a valid INR amount.');
    }

    $rupees = (int) $parts[1];
    $paise = isset($parts[2]) ? (int) str_pad($parts[2], 2, '0') : 0;
    return ($rupees * 100) + $paise;
}
