<?php
declare(strict_types=1);

function eventImageUploadsEnabled(): bool
{
    $setting = strtolower(trim((string) (getenv('EVENT_IMAGE_UPLOADS') ?: '')));
    if ($setting === '') {
        return getenv('VERCEL') !== '1';
    }

    return in_array($setting, ['1', 'true', 'yes', 'enabled'], true);
}

function saveEventImage(array $upload): string
{
    if (!eventImageUploadsEnabled()) {
        throw new RuntimeException('Poster uploads are disabled on this hosted demo.');
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !isset($upload['tmp_name'], $upload['size'])
        || !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('Choose a valid image file and try again.');
    }
    if ((int) $upload['size'] < 1 || (int) $upload['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Images must be 2 MB or smaller.');
    }

    $image = @getimagesize($upload['tmp_name']);
    $mime = is_array($image) ? ($image['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_array($image) || !isset($extensions[$mime])
        || (int) $image[0] < 1 || (int) $image[1] < 1
        || (int) $image[0] > 7000 || (int) $image[1] > 7000
        || (int) $image[0] * (int) $image[1] > 18_000_000) {
        throw new InvalidArgumentException('Use a JPEG, PNG, or WebP image up to 7,000 × 7,000 pixels.');
    }

    $directory = dirname(__DIR__, 2) . '/public/uploads/events';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not prepare event image storage.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Could not save the event image.');
    }
    return '/uploads/events/' . $filename;
}

function deleteEventImage(?string $imagePath): void
{
    if ($imagePath === null || !preg_match('~^/uploads/events/[a-f0-9]{32}\.(?:jpg|png|webp)$~', $imagePath)) {
        return;
    }
    $file = dirname(__DIR__, 2) . '/public' . $imagePath;
    if (is_file($file)) {
        unlink($file);
    }
}
