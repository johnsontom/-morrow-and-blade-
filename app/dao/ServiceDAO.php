<?php
/**
 * The treatment menu: haircuts, beard work, grooming, massage, manicure and
 * pedicure, grouped into the categories shown on /services.php.
 */

declare(strict_types=1);

final class ServiceDAO
{
    private const SELECT_WITH_CATEGORY = "
        SELECT
            s.id, s.slug, s.name, s.description, s.duration_minutes,
            s.price_pence, s.featured, s.display_order, s.active,
            s.category_id,
            c.name AS category_name,
            c.slug AS category_slug
        FROM services s
        LEFT JOIN service_categories c ON c.id = s.category_id
    ";

    /** Every active service, cheapest category order first. */
    public static function all(): array
    {
        return DB::all(
            self::SELECT_WITH_CATEGORY . '
             WHERE s.active = 1
             ORDER BY COALESCE(c.display_order, 999), s.display_order, s.name'
        );
    }

    /** Active services keyed by category name, in category order. */
    public static function groupedByCategory(): array
    {
        $grouped = [];

        foreach (self::all() as $service) {
            $grouped[$service['category_name'] ?? 'Other'][] = $service;
        }

        return $grouped;
    }

    /**
     * Active services that at least one active team member at this branch
     * carries out. Keeps the menu honest per salon.
     */
    public static function allAtSalon(string $salonId): array
    {
        if ($salonId === '') {
            return self::all();
        }

        return DB::all(
            self::SELECT_WITH_CATEGORY . '
             WHERE s.active = 1
               AND EXISTS (
                   SELECT 1
                   FROM barber_services bs
                   JOIN barbers b ON b.id = bs.barber_id
                   WHERE bs.service_id = s.id AND b.salon_id = ? AND b.active = 1
               )
             ORDER BY COALESCE(c.display_order, 999), s.display_order, s.name',
            [$salonId]
        );
    }

    /** Active services at one branch, keyed by category name. */
    public static function groupedByCategoryAtSalon(string $salonId): array
    {
        $grouped = [];

        foreach (self::allAtSalon($salonId) as $service) {
            $grouped[$service['category_name'] ?? 'Other'][] = $service;
        }

        return $grouped;
    }

    public static function categories(): array
    {
        return DB::all('SELECT * FROM service_categories WHERE active = 1 ORDER BY display_order, name');
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one(self::SELECT_WITH_CATEGORY . ' WHERE s.slug = ? LIMIT 1', [$slug]);
    }

    /** Accepts either a slug or a UUID, so links can be built either way. */
    public static function find(string $slugOrId): ?array
    {
        $slugOrId = trim($slugOrId);

        if ($slugOrId === '') {
            return null;
        }

        return DB::one(
            self::SELECT_WITH_CATEGORY . ' WHERE s.slug = ? OR s.id = ? LIMIT 1',
            [$slugOrId, $slugOrId]
        );
    }

    /** Services a specific team member can perform. */
    public static function forStaff(string $staffId): array
    {
        return DB::all(
            self::SELECT_WITH_CATEGORY . '
             JOIN barber_services bs ON bs.service_id = s.id
             WHERE bs.barber_id = ? AND s.active = 1
             ORDER BY COALESCE(c.display_order, 999), s.display_order',
            [$staffId]
        );
    }

    // ----------------------------------------------------------- owner tools

    /** Every treatment including the hidden ones, for the owner's menu screen. */
    public static function allForAdmin(): array
    {
        return DB::all(
            self::SELECT_WITH_CATEGORY . '
             ORDER BY COALESCE(c.display_order, 999), s.display_order, s.name'
        );
    }

    /** Categories including any that are switched off. */
    public static function categoriesForAdmin(): array
    {
        return DB::all('SELECT * FROM service_categories ORDER BY display_order, name');
    }

    public static function byId(string $id): ?array
    {
        return DB::one(self::SELECT_WITH_CATEGORY . ' WHERE s.id = ? LIMIT 1', [$id]);
    }

    /**
     * Create a treatment, or update one when $id is given.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool, message:string}
     */
    public static function save(?string $id, array $input): array
    {
        $clean = self::clean($input);

        if ($clean['errors'] !== []) {
            return ['ok' => false, 'message' => implode(' ', $clean['errors'])];
        }

        $values = $clean['values'];

        if ($id === null || $id === '') {
            $newId = AccountDAO::uuid();
            $values['slug'] = self::uniqueSlug($values['slug'], $newId);

            DB::run(
                'INSERT INTO services
                   (id, slug, category_id, name, description, duration_minutes, price_pence, featured, active, display_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $newId,
                    $values['slug'],
                    $values['category_id'],
                    $values['name'],
                    $values['description'],
                    $values['duration_minutes'],
                    $values['price_pence'],
                    $values['featured'],
                    $values['active'],
                    $values['display_order'],
                ]
            );

            self::syncBarbers($newId, $values['barbers']);

            return [
                'ok' => true,
                'message' => $values['name'] . ' is on the menu.'
                    . ($values['barbers'] === [] ? ' Nobody is assigned to it yet, so it cannot be booked.' : ''),
            ];
        }

        if (self::byId($id) === null) {
            return ['ok' => false, 'message' => 'That treatment no longer exists.'];
        }

        $values['slug'] = self::uniqueSlug($values['slug'], $id);

        DB::run(
            'UPDATE services
             SET slug = ?, category_id = ?, name = ?, description = ?, duration_minutes = ?,
                 price_pence = ?, featured = ?, active = ?, display_order = ?
             WHERE id = ?',
            [
                $values['slug'],
                $values['category_id'],
                $values['name'],
                $values['description'],
                $values['duration_minutes'],
                $values['price_pence'],
                $values['featured'],
                $values['active'],
                $values['display_order'],
                $id,
            ]
        );

        self::syncBarbers($id, $values['barbers']);

        return [
            'ok' => true,
            'message' => $values['name'] . ' is updated.'
                . ($values['barbers'] === [] ? ' Nobody is assigned to it, so it cannot be booked.' : ''),
        ];
    }

    /**
     * Remove a treatment. Anything with appointments against it is kept, because
     * past bookings still need their name and price.
     *
     * @return array{ok:bool, message:string}
     */
    public static function remove(string $id): array
    {
        $service = self::byId($id);

        if ($service === null) {
            return ['ok' => false, 'message' => 'That treatment no longer exists.'];
        }

        $booked = (int) (DB::one('SELECT COUNT(*) AS total FROM appointments WHERE service_id = ?', [$id])['total'] ?? 0);

        if ($booked > 0) {
            return [
                'ok' => false,
                'message' => $service['name'] . ' has ' . $booked . ' appointment'
                    . ($booked === 1 ? '' : 's')
                    . ' against it, so it cannot be deleted. Hide it instead to take it off the website.',
            ];
        }

        DB::run('DELETE FROM barber_services WHERE service_id = ?', [$id]);
        DB::run('DELETE FROM services WHERE id = ?', [$id]);

        return ['ok' => true, 'message' => $service['name'] . ' was deleted.'];
    }

    /** Show or hide a treatment on the public site. */
    public static function setActive(string $id, bool $active): array
    {
        $service = self::byId($id);

        if ($service === null) {
            return ['ok' => false, 'message' => 'That treatment no longer exists.'];
        }

        DB::run('UPDATE services SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);

        return [
            'ok' => true,
            'message' => $service['name'] . ($active ? ' is showing on the website again.' : ' is hidden from the website.'),
        ];
    }

    /** Ids of the team members who perform this treatment, as a lookup map. */
    public static function barberIdsFor(string $serviceId): array
    {
        $ids = [];

        foreach (DB::all('SELECT barber_id FROM barber_services WHERE service_id = ?', [$serviceId]) as $row) {
            $ids[(string) $row['barber_id']] = true;
        }

        return $ids;
    }

    /** How many team members perform each treatment, keyed by service id. */
    public static function barberCounts(): array
    {
        $counts = [];

        foreach (DB::all('SELECT service_id, COUNT(*) AS total FROM barber_services GROUP BY service_id') as $row) {
            $counts[(string) $row['service_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Replace who performs a treatment. A treatment nobody offers never reaches
     * the booking form, so this runs on every save.
     *
     * @param array<int,string> $barberIds
     */
    private static function syncBarbers(string $serviceId, array $barberIds): void
    {
        DB::run('DELETE FROM barber_services WHERE service_id = ?', [$serviceId]);

        foreach (array_unique($barberIds) as $barberId) {
            $barberId = trim((string) $barberId);

            if ($barberId === '' || DB::one('SELECT id FROM barbers WHERE id = ? LIMIT 1', [$barberId]) === null) {
                continue;
            }

            DB::run('INSERT INTO barber_services (barber_id, service_id) VALUES (?, ?)', [$barberId, $serviceId]);
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{errors:array<int,string>, values:array<string,mixed>}
     */
    private static function clean(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            $errors[] = 'Give the treatment a name.';
        } elseif (mb_strlen($name) > 150) {
            $errors[] = 'Keep the name under 150 characters.';
        }

        $description = trim((string) ($input['description'] ?? ''));

        if (mb_strlen($description) > 1000) {
            $errors[] = 'Keep the description under 1000 characters.';
        }

        $duration = (int) ($input['duration_minutes'] ?? 0);

        if ($duration < 10 || $duration > 240) {
            $errors[] = 'Duration has to be between 10 and 240 minutes.';
        }

        $price = self::toPence((string) ($input['price'] ?? ''));

        if ($price === null || $price < 1) {
            $errors[] = 'Enter a price above zero, like 48 or 48.50.';
            $price = 0;
        }

        $categoryId = trim((string) ($input['category_id'] ?? ''));

        if ($categoryId !== '' && DB::one('SELECT id FROM service_categories WHERE id = ? LIMIT 1', [$categoryId]) === null) {
            $errors[] = 'Pick a category from the list.';
            $categoryId = '';
        }

        $slug = trim((string) ($input['slug'] ?? ''));

        $barbers = is_array($input['barbers'] ?? null) ? array_values($input['barbers']) : [];

        return [
            'errors' => $errors,
            'values' => [
                'slug' => self::slugify($slug !== '' ? $slug : $name),
                'category_id' => $categoryId === '' ? null : $categoryId,
                'name' => $name,
                'description' => $description,
                'duration_minutes' => $duration,
                'price_pence' => $price,
                'featured' => empty($input['featured']) ? 0 : 1,
                'active' => empty($input['active']) ? 0 : 1,
                'display_order' => (int) ($input['display_order'] ?? 0),
                'barbers' => $barbers,
            ],
        ];
    }

    /** Handles 48, 48.50, GBP 48.50 and 1,200.00. Null when it is not a number. */
    private static function toPence(string $raw): ?int
    {
        $raw = str_replace([',', ' ', 'GBP', 'gbp', '£', '$'], '', trim($raw));

        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (int) round(((float) $raw) * 100);
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'treatment' : substr($value, 0, 100);
    }

    /** Keeps the public address unique, so two treatments never share a page. */
    private static function uniqueSlug(string $slug, string $exceptId): string
    {
        if (!self::slugTaken($slug, $exceptId)) {
            return $slug;
        }

        $base = substr($slug, 0, 90);
        $suffix = 2;

        while (self::slugTaken($base . '-' . $suffix, $exceptId)) {
            $suffix++;
        }

        return $base . '-' . $suffix;
    }

    private static function slugTaken(string $slug, string $exceptId): bool
    {
        $row = DB::one('SELECT id FROM services WHERE slug = ? LIMIT 1', [$slug]);

        return $row !== null && (string) $row['id'] !== $exceptId;
    }
}
