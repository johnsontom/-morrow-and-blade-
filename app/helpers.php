<?php
/**
 * Small view helpers shared by every page.
 */

declare(strict_types=1);

/** Escape a value for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL that works from any page in the app. */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Link to a stylesheet or script inside /assets. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Resolve a team member's photo for use in a src attribute.
 *
 * The column holds either a full web address the member pasted in, or the
 * path of a file they uploaded, which is stored relative to the app.
 */
function staff_photo_url(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $value) === 1 || str_starts_with($value, '/')
        || str_starts_with($value, 'data:')) {
        return $value;
    }

    return url($value);
}

/** Prices are stored in pence. */
function money(int $pence): string
{
    return SITE_CURRENCY . number_format($pence / 100, 2);
}

/** 95 -> "1 hr 35 min", 45 -> "45 min". */
function duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;

    return $rest === 0
        ? $hours . ' hr'
        : $hours . ' hr ' . $rest . ' min';
}

/** True when the given file is the page being viewed, for nav highlighting. */
function is_current(string $file): bool
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $file;
}

/** Decode a JSON array column safely. */
function json_list(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

/** CSS class for a barber status badge. */
function status_class(string $status): string
{
    return match ($status) {
        'available', 'confirmed' => 'badge badge-available',
        'busy', 'in_progress' => 'badge badge-busy',
        'booked', 'pending' => 'badge badge-booked',
        'completed' => 'badge badge-done',
        'cancelled', 'no_show' => 'badge badge-offline',
        default => 'badge badge-offline',
    };
}

/** A human label for a staff_kind value. */
function staff_kind_label(string $kind): string
{
    return match ($kind) {
        'beautician' => 'Beauty',
        'nail_technician' => 'Nails',
        'therapist' => 'Massage',
        default => 'Barber',
    };
}

/** Friendly name for the role on a portal login. */
function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Owner',
        'manager' => 'Manager',
        'barber' => 'Barber',
        'staff' => 'Staff',
        'pending' => 'Awaiting access',
        default => ucfirst($role),
    };
}

/** Short area label for the salon, e.g. "Soho, London". */
function salon_area_label(?array $salon): string
{
    $area = salon_name_area($salon['name'] ?? '');

    if ($area === '' && class_exists('BranchDAO')) {
        $branch = BranchDAO::primary();
        $area = is_array($branch) ? salon_name_area($branch['name'] ?? '') : '';
    }

    if ($area === '') {
        return 'Covent Garden, London';
    }

    $city = trim((string) ($salon['city'] ?? ''));

    return $area . ', ' . ($city !== '' ? $city : 'London');
}

/** "Morrow & Blade Soho" -> "Soho". */
function salon_name_area(?string $name): string
{
    return trim(str_replace(['Morrow & Blade', 'Morrow and Blade'], '', (string) $name));
}
