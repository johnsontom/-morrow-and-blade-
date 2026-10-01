<?php
/**
 * Salon branches and the "closest salon" lookup.
 */

declare(strict_types=1);

final class BranchDAO
{
    /** Cookie that remembers which branch the visitor is browsing. */
    public const COOKIE = 'mb_branch';

    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM salons';

        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }

        $sql .= ' ORDER BY is_primary DESC, display_order, name';

        return array_map([self::class, 'decorate'], DB::all($sql));
    }

    public static function bySlug(string $slug): ?array
    {
        $row = DB::one('SELECT * FROM salons WHERE slug = ? LIMIT 1', [$slug]);

        return $row === null ? null : self::decorate($row);
    }

    public static function primary(): ?array
    {
        $row = DB::one('SELECT * FROM salons WHERE is_primary = 1 AND active = 1 ORDER BY display_order LIMIT 1');

        if ($row === null) {
            $row = DB::one('SELECT * FROM salons WHERE active = 1 ORDER BY display_order LIMIT 1');
        }

        return $row === null ? null : self::decorate($row);
    }

    /**
     * The branch the visitor should be shown.
     *
     *   1. ?branch=<slug> in the URL, which also remembers the choice
     *   2. the mb_branch cookie from an earlier visit
     *   3. the flagship
     *
     * Nothing here guesses a location on its own - the browser does that on
     * the client and then asks for ?branch=<slug>.
     */
    public static function current(): ?array
    {
        $slug = trim((string) ($_GET['branch'] ?? ''));

        if ($slug !== '') {
            $branch = self::bySlug($slug);

            if ($branch !== null) {
                self::remember($slug);

                return $branch;
            }
        }

        $slug = trim((string) ($_COOKIE[self::COOKIE] ?? ''));

        if ($slug !== '') {
            $branch = self::bySlug($slug);

            if ($branch !== null) {
                return $branch;
            }
        }

        return self::primary();
    }

    /** False until the visitor picks a branch or shares their location. */
    public static function hasChosen(): bool
    {
        return trim((string) ($_COOKIE[self::COOKIE] ?? '')) !== '';
    }

    /** Remember the visitor's branch for a month. */
    public static function remember(string $slug): void
    {
        $_COOKIE[self::COOKIE] = $slug;

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $slug, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/',
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Branches sorted by distance from a point, using the haversine formula.
     * MariaDB has no spatial index here, so the maths runs in SQL - fine for
     * a handful of branches.
     *
     * @return array<int, array> each row plus distance_miles / distance_km
     */
    public static function nearest(float $latitude, float $longitude, int $limit = 5): array
    {
        // Positional placeholders: with emulated prepares off, MySQL will not
        // accept the same named parameter twice in one statement.
        $sql = 'SELECT *, (
                    3958.7613 * ACOS(
                        LEAST(1, GREATEST(-1,
                            COS(RADIANS(?)) * COS(RADIANS(latitude)) *
                            COS(RADIANS(longitude) - RADIANS(?)) +
                            SIN(RADIANS(?)) * SIN(RADIANS(latitude))
                        ))
                    )
                ) AS distance_miles
                FROM salons
                WHERE active = 1
                ORDER BY distance_miles ASC
                LIMIT ' . max(1, min(50, $limit));

        $rows = DB::all($sql, [$latitude, $longitude, $latitude]);

        foreach ($rows as $index => $row) {
            $row = self::decorate($row);
            $row['distance_miles'] = round((float) $row['distance_miles'], 1);
            $row['distance_km'] = round((float) $row['distance_miles'] * 1.609344, 1);
            $rows[$index] = $row;
        }

        return $rows;
    }

    /** Team members working at a branch. */
    public static function team(string $salonId): array
    {
        return DB::all(
            'SELECT * FROM barbers WHERE salon_id = ? AND active = 1 ORDER BY display_order, name',
            [$salonId]
        );
    }

    public static function teamCounts(): array
    {
        $counts = [];

        foreach (DB::all('SELECT salon_id, COUNT(*) AS total FROM barbers WHERE active = 1 AND salon_id IS NOT NULL GROUP BY salon_id') as $row) {
            $counts[$row['salon_id']] = (int) $row['total'];
        }

        return $counts;
    }

    private static function decorate(array $row): array
    {
        $row['opening_hours_decoded'] = json_list($row['opening_hours'] ?? null);
        $row['map_url'] = 'https://www.google.com/maps/dir/?api=1&destination='
            . urlencode($row['latitude'] . ',' . $row['longitude']);
        $row['map_embed_url'] = 'https://www.openstreetmap.org/export/embed.html?bbox='
            . urlencode(((float) $row['longitude'] - 0.008) . ',' . ((float) $row['latitude'] - 0.005) . ','
                . ((float) $row['longitude'] + 0.008) . ',' . ((float) $row['latitude'] + 0.005))
            . '&layer=mapnik&marker=' . urlencode($row['latitude'] . ',' . $row['longitude']);

        return $row;
    }
}
