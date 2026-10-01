<?php
/**
 * Reporting for the owner area: what each branch has taken, what each member
 * of the team has taken, and the full appointment history behind those figures.
 *
 * Money lives on the appointment row as price_pence_snapshot, which is the
 * treatment price at the moment it was booked, so these figures do not move
 * when the menu prices are edited later. Only 'completed' appointments count
 * as money taken; cancelled and no-show rows are reported as their own columns
 * so they can be seen without inflating the totals.
 */

declare(strict_types=1);

final class ReportDAO
{
    /** How many history rows to show per page. */
    public const PAGE_SIZE = 50;

    /** Every status an appointment can hold, for the history filter. */
    public const STATUSES = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

    /**
     * Takings for every branch, in display order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function branchEarnings(): array
    {
        return DB::all(
            "SELECT s.id,
                    s.slug,
                    s.name,
                    COUNT(DISTINCT b.id) AS staff,
                    COUNT(a.id) AS appointments,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price_pence_snapshot ELSE 0 END), 0) AS earnings_pence,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' AND a.starts_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY
                                      THEN a.price_pence_snapshot ELSE 0 END), 0) AS earnings_30d_pence,
                    MAX(CASE WHEN a.status = 'completed' THEN a.starts_at END) AS last_sale
             FROM salons s
             LEFT JOIN barbers b ON b.salon_id = s.id
             LEFT JOIN appointments a ON a.barber_id = b.id
             GROUP BY s.id, s.slug, s.name, s.display_order
             ORDER BY s.display_order, s.name"
        );
    }

    /**
     * Takings for every member of staff, biggest earner first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function workerTakings(): array
    {
        return DB::all(
            "SELECT b.id,
                    b.slug,
                    b.name,
                    b.role,
                    b.active,
                    s.name AS branch_name,
                    COUNT(a.id) AS appointments,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
                    COALESCE(SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END), 0) AS no_shows,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price_pence_snapshot ELSE 0 END), 0) AS earnings_pence,
                    MAX(CASE WHEN a.status = 'completed' THEN a.starts_at END) AS last_sale
             FROM barbers b
             LEFT JOIN salons s ON s.id = b.salon_id
             LEFT JOIN appointments a ON a.barber_id = b.id
             GROUP BY b.id, b.slug, b.name, b.role, b.active, s.name
             ORDER BY earnings_pence DESC, b.name"
        );
    }

    /**
     * The money columns for whichever slice of the history is being shown.
     *
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public static function historySummary(array $filters): array
    {
        [$where, $params] = self::historyFilter($filters);

        $row = DB::one(
            "SELECT COUNT(*) AS rows_,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price_pence_snapshot ELSE 0 END), 0) AS earnings_pence,
                    COALESCE(SUM(CASE WHEN a.status = 'cancelled' THEN a.price_pence_snapshot ELSE 0 END), 0) AS lost_pence
             FROM appointments a
             JOIN barbers b ON b.id = a.barber_id
             $where",
            $params
        );

        return $row ?? ['rows_' => 0, 'earnings_pence' => 0, 'lost_pence' => 0];
    }

    /**
     * One page of appointment history, newest first.
     *
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function history(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::historyFilter($filters);

        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        return DB::all(
            "SELECT a.reference,
                    a.starts_at,
                    a.ends_at,
                    a.status,
                    a.price_pence_snapshot,
                    a.service_name_snapshot,
                    a.created_at,
                    b.name AS barber_name,
                    b.slug AS barber_slug,
                    sv.name AS service_name,
                    c.name AS customer_name,
                    sa.name AS branch_name
             FROM appointments a
             JOIN barbers b ON b.id = a.barber_id
             JOIN customers c ON c.id = a.customer_id
             LEFT JOIN services sv ON sv.id = a.service_id
             LEFT JOIN salons sa ON sa.id = b.salon_id
             $where
             ORDER BY a.starts_at DESC, a.reference DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
    }

    /**
     * Turn the history filters into a WHERE clause and its parameters.
     *
     * @param array<string, string> $filters
     * @return array{0:string, 1:array<int, string>}
     */
    private static function historyFilter(array $filters): array
    {
        $clauses = [];
        $params = [];

        $barber = trim((string) ($filters['barber'] ?? ''));
        if ($barber !== '') {
            $clauses[] = 'b.slug = ?';
            $params[] = $barber;
        }

        $branch = trim((string) ($filters['branch'] ?? ''));
        if ($branch !== '') {
            $clauses[] = 'b.salon_id = ?';
            $params[] = $branch;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, self::STATUSES, true)) {
            $clauses[] = 'a.status = ?';
            $params[] = $status;
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if (self::isDate($from)) {
            $clauses[] = 'a.starts_at >= ?';
            $params[] = $from . ' 00:00:00';
        }

        // The end date is inclusive, so take everything before the next midnight.
        $to = trim((string) ($filters['to'] ?? ''));
        if (self::isDate($to)) {
            $clauses[] = 'a.starts_at < ?';
            $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    private static function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}
