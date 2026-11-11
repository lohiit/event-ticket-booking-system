<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eventArtwork(string $title): array
{
    $artwork = [
        'city beats live' => ['/assets/images/city-beats-live.svg', 'Live music stage in violet and coral lights'],
        'satrang: classical fusion' => ['/assets/images/satrang-classical-fusion.svg', 'Classical dance and music inspired by Indian colors'],
    ];
    return $artwork[strtolower(trim($title))] ?? ['/assets/images/event-default.svg', 'Colorful event artwork'];
}
