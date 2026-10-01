<?php
/**
 * "Nearest salon" lookup. The browser sends the visitor's coordinates and we
 * return the branches ordered by distance.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$latitude = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if ($latitude === false || $latitude === null || $longitude === false || $longitude === null
    || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Send lat and lng.']);
    exit;
}

$branches = [];

foreach (BranchDAO::nearest((float) $latitude, (float) $longitude, 10) as $branch) {
    $branches[] = [
        'name' => $branch['name'],
        'slug' => $branch['slug'],
        'address' => trim($branch['address_line_1'] . ', ' . $branch['city'] . ' ' . $branch['postcode'], ', '),
        'phone' => $branch['phone'],
        'distance_miles' => $branch['distance_miles'],
        'distance_km' => $branch['distance_km'],
        'directions_url' => $branch['map_url'],
        'url' => url('branches.php?branch=' . urlencode((string) $branch['slug'])),
    ];
}

echo json_encode(['ok' => true, 'branches' => $branches, 'nearest' => $branches[0] ?? null]);