<?php
/**
 * The salon's own details: address, contact information, opening hours and the
 * coordinates used by the map on the contact page.
 */

declare(strict_types=1);

final class SalonDAO
{
    public static function primary(): ?array
    {
        $salon = DB::one('SELECT * FROM salon_settings WHERE singleton = 1 LIMIT 1');

        if ($salon === null) {
            return null;
        }

        $salon['opening_hours_decoded'] = json_list($salon['opening_hours']);
        $salon['full_address'] = trim(
            $salon['address_line_1'] . ', ' . $salon['address_line_2'] . ', ' . $salon['city'] . ' ' . $salon['postcode'],
            ' ,'
        );

        return $salon;
    }

    /** Opening hours for one weekday name, e.g. "Monday". */
    public static function hoursFor(?array $salon, string $day): ?array
    {
        foreach (($salon['opening_hours_decoded'] ?? []) as $entry) {
            if (($entry['day'] ?? '') === $day) {
                return $entry;
            }
        }

        return null;
    }

    /** True when the salon is open right now in its own timezone. */
    public static function isOpenNow(?array $salon): bool
    {
        if ($salon === null) {
            return false;
        }

        $timezone = new DateTimeZone($salon['timezone'] ?: SITE_TIMEZONE);
        $now = new DateTimeImmutable('now', $timezone);
        $today = self::hoursFor($salon, $now->format('l'));

        if ($today === null || empty($today['isOpen'])) {
            return false;
        }

        [$opens, $closes] = array_pad(explode(' - ', (string) ($today['hours'] ?? '')), 2, null);

        if ($opens === null || $closes === null) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= trim($opens) && $time < trim($closes);
    }
}