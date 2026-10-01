<?php
/**
 * Everything a signed-in team member can change about their own working
 * life: profile, rota, days off, live status and which services they offer.
 *
 * Every method takes the barber id from the session, never from the form,
 * so one barber cannot edit another barber's page.
 */

declare(strict_types=1);

final class StaffDAO
{
    // -------------------------------------------------------------- profile

    public static function barber(string $barberId): ?array
    {
        return DB::one('SELECT * FROM barbers WHERE id = ? LIMIT 1', [$barberId]);
    }

    public static function updateProfile(string $barberId, array $data): void
    {
        DB::run(
            'UPDATE barbers
             SET name = ?, role = ?, bio = ?, photo_url = ?, specialties = ?,
                 years_experience = ?, salon_id = ?, active = ?
             WHERE id = ?',
            [
                $data['name'],
                $data['role'],
                $data['bio'],
                $data['photo_url'],
                json_encode(array_values(array_filter(array_map('trim', $data['specialties'] ?? []))), JSON_UNESCAPED_SLASHES),
                (int) $data['years_experience'],
                $data['salon_id'] ?: null,
                !empty($data['active']) ? 1 : 0,
                $barberId,
            ]
        );
    }

    /** The services this barber can perform, with a flag for pricing overrides. */
    public static function offeredServiceIds(string $barberId): array
    {
        $ids = [];

        foreach (DB::all('SELECT service_id FROM barber_services WHERE barber_id = ?', [$barberId]) as $row) {
            $ids[$row['service_id']] = true;
        }

        return $ids;
    }

    public static function setOfferedServices(string $barberId, array $serviceIds): void
    {
        DB::run('DELETE FROM barber_services WHERE barber_id = ?', [$barberId]);

        foreach (array_unique($serviceIds) as $serviceId) {
            if ($serviceId === '') {
                continue;
            }

            DB::run('INSERT INTO barber_services (barber_id, service_id) VALUES (?, ?)', [$barberId, $serviceId]);
        }
    }

    // ------------------------------------------------------------ rota week

    /** Seven rows keyed by weekday (0=Sunday .. 6=Saturday). */
    public static function week(string $barberId): array
    {
        $existing = [];

        foreach (DB::all('SELECT * FROM working_hours WHERE barber_id = ?', [$barberId]) as $row) {
            $existing[(int) $row['weekday']] = $row;
        }

        $week = [];

        for ($day = 0; $day <= 6; $day++) {
            $week[$day] = $existing[$day] ?? [
                'id' => null,
                'barber_id' => $barberId,
                'weekday' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'is_working' => $day === 0 ? 0 : 1,
            ];
        }

        return $week;
    }

    /**
     * @param array<int, array{start:string,end:string,working:bool}> $days
     */
    public static function saveWeek(string $barberId, array $days): void
    {
        foreach ($days as $weekday => $day) {
            $weekday = (int) $weekday;

            if ($weekday < 0 || $weekday > 6) {
                continue;
            }

            $working = !empty($day['working']) ? 1 : 0;
            $start = self::time($day['start'] ?? '09:00');
            $end = self::time($day['end'] ?? '18:00');

            if ($working && $end <= $start) {
                $end = (new DateTimeImmutable($start))->modify('+8 hours')->format('H:i:s');
            }

            DB::run(
                'INSERT INTO working_hours (id, barber_id, weekday, start_time, end_time, is_working)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), is_working = VALUES(is_working)',
                [AccountDAO::uuid(), $barberId, $weekday, $start, $end, $working]
            );
        }
    }

    // --------------------------------------------------------- days off

    public static function exceptions(string $barberId, int $limit = 40): array
    {
        return DB::all(
            'SELECT * FROM schedule_exceptions
             WHERE barber_id = ? AND exception_date >= (CURRENT_DATE - INTERVAL 1 DAY)
             ORDER BY exception_date, start_time
             LIMIT ' . max(1, min(200, $limit)),
            [$barberId]
        );
    }

    public static function addException(
        string $barberId,
        string $date,
        ?string $start,
        ?string $end,
        bool $available,
        string $note
    ): void {
        DB::run(
            'INSERT INTO schedule_exceptions (id, barber_id, exception_date, start_time, end_time, is_available, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                AccountDAO::uuid(),
                $barberId,
                $date,
                $start !== null && $start !== '' ? self::time($start) : null,
                $end !== null && $end !== '' ? self::time($end) : null,
                $available ? 1 : 0,
                $note,
            ]
        );
    }

    public static function deleteException(string $barberId, string $exceptionId): void
    {
        DB::run('DELETE FROM schedule_exceptions WHERE id = ? AND barber_id = ?', [$exceptionId, $barberId]);
    }

    // -------------------------------------------------------- live status

    public static function currentOverride(string $barberId): ?array
    {
        return DB::one(
            'SELECT * FROM barber_status_overrides
             WHERE barber_id = ? AND (valid_until IS NULL OR valid_until > NOW())
             ORDER BY updated_at DESC LIMIT 1',
            [$barberId]
        );
    }

    public static function setStatus(string $barberId, ?string $status, string $reason, ?string $until, string $profileId): void
    {
        DB::run('DELETE FROM barber_status_overrides WHERE barber_id = ?', [$barberId]);

        if ($status === null || $status === '') {
            return;
        }

        DB::run(
            'INSERT INTO barber_status_overrides (id, barber_id, status, reason, valid_until, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                AccountDAO::uuid(),
                $barberId,
                $status,
                $reason,
                $until !== null && $until !== '' ? $until : null,
                $profileId,
            ]
        );
    }

    // ------------------------------------------------------- appointments

    public static function appointments(string $barberId, string $scope = 'upcoming', int $limit = 100): array
    {
        $where = match ($scope) {
            'today' => 'AND DATE(a.starts_at) = CURDATE()',
            'past' => 'AND a.ends_at < NOW()',
            'cancelled' => "AND a.status = 'cancelled'",
            default => 'AND a.ends_at >= NOW()',
        };

        $order = $scope === 'past' ? 'DESC' : 'ASC';

        return DB::all(
            "SELECT a.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    s.name AS service_name
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             JOIN services s ON s.id = a.service_id
             WHERE a.barber_id = ? $where
             ORDER BY a.starts_at $order
             LIMIT " . max(1, min(500, $limit)),
            [$barberId]
        );
    }

    public static function updateAppointmentStatus(string $barberId, string $appointmentId, string $status): bool
    {
        $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $affected = DB::run(
            'UPDATE appointments
             SET status = ?, cancelled_at = IF(? = \'cancelled\', NOW(), cancelled_at)
             WHERE id = ? AND barber_id = ?',
            [$status, $status, $appointmentId, $barberId]
        );

        return $affected > 0;
    }

    public static function stats(string $barberId): array
    {
        return [
            'today' => (int) (DB::one(
                'SELECT COUNT(*) AS total FROM appointments
                 WHERE barber_id = ? AND DATE(starts_at) = CURDATE() AND status <> \'cancelled\'',
                [$barberId]
            )['total'] ?? 0),
            'upcoming' => (int) (DB::one(
                'SELECT COUNT(*) AS total FROM appointments
                 WHERE barber_id = ? AND ends_at >= NOW() AND status IN (\'pending\', \'confirmed\')',
                [$barberId]
            )['total'] ?? 0),
            'completed' => (int) (DB::one(
                'SELECT COUNT(*) AS total FROM appointments WHERE barber_id = ? AND status = \'completed\'',
                [$barberId]
            )['total'] ?? 0),
            'revenue' => (int) (DB::one(
                'SELECT COALESCE(SUM(price_pence_snapshot), 0) AS total FROM appointments
                 WHERE barber_id = ? AND status = \'completed\' AND starts_at >= (CURRENT_DATE - INTERVAL 30 DAY)',
                [$barberId]
            )['total'] ?? 0),
            'unread' => (int) (DB::one(
                'SELECT COALESCE(SUM(barber_unread), 0) AS total FROM conversations WHERE barber_id = ?',
                [$barberId]
            )['total'] ?? 0),
        ];
    }

    private static function time(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value . ':00' : $value;
    }
}
