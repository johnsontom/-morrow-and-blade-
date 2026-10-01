<?php
/**
 * The team, plus the live availability logic.
 *
 * The availability rules were originally a PostgreSQL function
 * (get_barber_availability). The precedence is preserved here:
 *
 *   1. an appointment happening right now            -> busy
 *   2. an active manual status override              -> whatever the override says
 *   3. outside working hours, or a day off exception -> offline
 *   4. an appointment starting within 60 minutes     -> booked
 *   5. otherwise                                     -> available
 */

declare(strict_types=1);

final class BarberDAO
{
    /** Every active team member, in display order, optionally at one branch. */
    public static function all(?string $salonId = null): array
    {
        return array_map([self::class, 'decorate'], DB::all(
            self::teamSql($salonId) . ' ORDER BY display_order, name',
            self::teamParams($salonId)
        ));
    }

    /** The WHERE clause shared by the team queries. */
    private static function teamSql(?string $salonId): string
    {
        $sql = 'SELECT * FROM barbers WHERE active = 1';

        if ($salonId !== null && $salonId !== '') {
            $sql .= ' AND salon_id = ?';
        }

        return $sql;
    }

    /**
     * @return array<int,string>
     */
    private static function teamParams(?string $salonId): array
    {
        return $salonId !== null && $salonId !== '' ? [$salonId] : [];
    }

    public static function bySlug(string $slug): ?array
    {
        $barber = DB::one('SELECT * FROM barbers WHERE slug = ? LIMIT 1', [$slug]);

        return $barber === null ? null : self::decorate($barber);
    }

    /** Accepts either a slug or a UUID, so links can be built either way. */
    public static function find(string $slugOrId): ?array
    {
        $slugOrId = trim($slugOrId);

        if ($slugOrId === '') {
            return null;
        }

        $barber = DB::one('SELECT * FROM barbers WHERE slug = ? OR id = ? LIMIT 1', [$slugOrId, $slugOrId]);

        return $barber === null ? null : self::decorate($barber);
    }

    /**
     * The team with a live status attached, used by the homepage and the
     * team page. $at lets you ask "what would this look like at 15:00?".
     */
    public static function withAvailability(?DateTimeImmutable $at = null, ?string $salonId = null): array
    {
        $timezone = new DateTimeZone(SITE_TIMEZONE);
        $now = ($at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $local = $now->setTimezone($timezone);

        $atUtc = $now->format('Y-m-d H:i:s');
        $localDate = $local->format('Y-m-d');
        $localTime = $local->format('H:i:s');
        $weekday = (int) $local->format('w');
        $soonCutoff = $now->modify('+60 minutes')->format('Y-m-d H:i:s');

        $barbers = DB::all(
            self::teamSql($salonId) . ' ORDER BY display_order, name',
            self::teamParams($salonId)
        );

        if ($barbers === []) {
            return [];
        }

        // working hours for today only
        $hours = [];
        foreach (DB::all('SELECT * FROM working_hours WHERE weekday = ?', [$weekday]) as $row) {
            $hours[$row['barber_id']] = $row;
        }

        // the most recently updated override per team member, if still valid
        $overrides = [];
        foreach (DB::all(
            'SELECT * FROM barber_status_overrides
             WHERE valid_until IS NULL OR valid_until > ?
             ORDER BY updated_at DESC',
            [$atUtc]
        ) as $row) {
            $overrides[$row['barber_id']] ??= $row;
        }

        // live and upcoming appointments
        $current = [];
        $next = [];
        foreach (DB::all(
            "SELECT barber_id, starts_at, ends_at, status
             FROM appointments
             WHERE status IN ('pending', 'confirmed', 'in_progress')
               AND ends_at > ?
             ORDER BY starts_at",
            [$atUtc]
        ) as $row) {
            $barberId = $row['barber_id'];

            if ($row['starts_at'] <= $atUtc && $row['ends_at'] > $atUtc) {
                $current[$barberId] ??= $row;
            } elseif ($row['starts_at'] > $atUtc && $row['status'] !== 'in_progress') {
                $next[$barberId] ??= $row;
            }
        }

        // days off and one-off schedule exceptions covering right now
        $exceptions = [];
        foreach (DB::all(
            'SELECT * FROM schedule_exceptions WHERE exception_date = ? AND is_available = 0',
            [$localDate]
        ) as $row) {
            $exceptions[$row['barber_id']][] = $row;
        }

        $team = [];

        foreach ($barbers as $barber) {
            $barberId = $barber['id'];
            $workingHours = $hours[$barberId] ?? null;
            $override = $overrides[$barberId] ?? null;
            $working = $current[$barberId] ?? null;
            $upcoming = $next[$barberId] ?? null;

            $dayOff = false;
            foreach ($exceptions[$barberId] ?? [] as $exception) {
                if ($exception['start_time'] === null
                    || ($localTime >= $exception['start_time'] && $localTime <= $exception['end_time'])) {
                    $dayOff = true;
                    break;
                }
            }

            $outsideHours = $workingHours === null
                || (int) $workingHours['is_working'] !== 1
                || $localTime < $workingHours['start_time']
                || $localTime >= $workingHours['end_time'];

            if ($working !== null) {
                $status = 'busy';
                $message = 'Currently with a customer';
            } elseif ($override !== null) {
                $status = $override['status'];
                $message = self::overrideMessage($override);
            } elseif ($outsideHours || $dayOff) {
                $status = 'offline';
                $message = $dayOff || $workingHours === null || (int) $workingHours['is_working'] !== 1
                    ? 'Not working today'
                    : ($localTime < $workingHours['start_time'] ? 'Starts later today' : 'Finished for today');
            } elseif ($upcoming !== null && $upcoming['starts_at'] <= $soonCutoff) {
                $status = 'booked';
                $message = 'Appointment approaching';
            } else {
                $status = 'available';
                $message = 'Available now';
            }

            if ($working !== null) {
                $nextAvailableAt = $working['ends_at'];
            } elseif ($upcoming !== null) {
                $nextAvailableAt = $upcoming['ends_at'];
            } elseif ($workingHours !== null && $localTime < $workingHours['start_time']) {
                $nextAvailableAt = (new DateTimeImmutable(
                    $localDate . ' ' . $workingHours['start_time'],
                    $timezone
                ))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } else {
                $nextAvailableAt = $atUtc;
            }

            $barber = self::decorate($barber);
            $barber['status'] = $status;
            $barber['status_message'] = $message;
            $barber['next_available_at'] = $nextAvailableAt;
            $barber['next_available_label'] = self::relativeLabel($nextAvailableAt, $now);
            $barber['working_hours_label'] = $workingHours === null || (int) $workingHours['is_working'] !== 1
                ? 'Closed'
                : substr($workingHours['start_time'], 0, 5) . ' - ' . substr($workingHours['end_time'], 0, 5);

            $team[] = $barber;
        }

        return $team;
    }

    private static function overrideMessage(array $override): string
    {
        return match ($override['status']) {
            'available' => 'Available now',
            'busy' => 'Currently with a customer',
            'booked' => 'Appointment approaching',
            default => $override['reason'] !== '' ? $override['reason'] : 'Not currently working',
        };
    }

    /** "Until 14:30", "Today 16:00" or "Ask in salon". */
    private static function relativeLabel(string $utcTimestamp, DateTimeImmutable $now): string
    {
        $timezone = new DateTimeZone(SITE_TIMEZONE);
        $target = (new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC')))->setTimezone($timezone);
        $today = $now->setTimezone($timezone)->format('Y-m-d');

        if ($target->format('Y-m-d') === $today) {
            return 'Today ' . $target->format('H:i');
        }

        return $target->format('D j M, H:i');
    }

    /**
     * Adds a team member and returns the new id. The slug comes from the name,
     * so their public profile and booking links work straight away.
     *
     * @param array<string,mixed> $data
     */
    public static function create(array $data): string
    {
        $id = AccountDAO::uuid();
        $role = trim((string) ($data['role'] ?? ''));
        $salonId = trim((string) ($data['salon_id'] ?? ''));

        DB::run(
            'INSERT INTO barbers (id, slug, name, role, staff_kind, salon_id, bio, specialties,
                                  years_experience, active, display_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                self::uniqueSlug((string) $data['name']),
                $data['name'],
                $role !== '' ? $role : 'Barber',
                $data['staff_kind'],
                $salonId !== '' ? $salonId : null,
                (string) ($data['bio'] ?? ''),
                json_encode(array_values($data['specialties'] ?? []), JSON_UNESCAPED_SLASHES),
                (int) ($data['years_experience'] ?? 0),
                !empty($data['active']) ? 1 : 0,
                (int) ($data['display_order'] ?? 0),
            ]
        );

        return $id;
    }

    /** Monday to Saturday, 09:00-18:00, so a new member can be booked at once. */
    public static function seedWorkingWeek(string $barberId): void
    {
        foreach (range(1, 6) as $weekday) {
            DB::run(
                'INSERT INTO working_hours (id, barber_id, weekday, start_time, end_time, is_working)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [AccountDAO::uuid(), $barberId, $weekday, '09:00:00', '18:00:00']
            );
        }
    }

    /** A URL-safe slug that nothing else is using yet. */
    private static function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
        $base = $base !== '' ? $base : 'team-member';

        $slug = $base;
        $suffix = 2;

        while (DB::one('SELECT id FROM barbers WHERE slug = ? LIMIT 1', [$slug]) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /** Decode JSON columns and add a couple of display fields. */
    private static function decorate(array $barber): array
    {
        $barber['specialties_list'] = json_list($barber['specialties']);
        $barber['staff_kind_label'] = staff_kind_label($barber['staff_kind'] ?? 'barber');

        return $barber;
    }
}
