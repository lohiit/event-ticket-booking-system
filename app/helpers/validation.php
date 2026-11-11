<?php
declare(strict_types=1);

function formString(string $key, int $maxLength = 500): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return substr(trim($value), 0, $maxLength);
}

function isValidEmailAddress(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function localRedirectPath(mixed $candidate, string $fallback = '/index.php'): string
{
    if (!is_string($candidate) || $candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//')) {
        return $fallback;
    }

    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['host']) || isset($parts['scheme']) || isset($parts['user'])) {
        return $fallback;
    }

    return $candidate;
}
