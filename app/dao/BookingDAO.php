<?php
/**
 * Slot generation and booking creation.
 *
 * Port of the PostgreSQL functions get_available_slots() and
 * book_appointment(). Slots run every 30 minutes from the start of the working
 * day until the last slot that still finishes before closing time. A slot is
 * unavailable when it is in the past, blocked by a schedule exception, or
 * overlaps an appointment that is pending, confirmed or in progress.
 */

declare(strict_types=1);

final class BookingDAO
{
    public const SLOT_INTERVAL_MINUTES = 30;
    public const BOOKABLE_STATUSES = ['pending', 'confirmed', 'in_progress'];

    /**
     * Every slot for one day, whether free or not.
     *
     * @return array<int, array{time:string, starts_at:string, available:bool, reason:string}>
     */
    public static function availableSlots(string $barberId, string $serviceId, string $date): array
    {
        $timezone = new DateTimeZone(SITE_TIMEZONE);

        if (!self::isValidDate($date)) {
            return [];
        }

        $day = new DateTimeImmutable($date . ' 00:00:00', $timezone);
        $weekday = (int) $day->format('w');

        $service = DB::one(
            'SELECT s.duration_minutes
             FROM services s
             JOIN barber_services bs ON bs.service_id = s.id
             JOIN barbers b ON b.id = bs.barber_id
             WHERE s.id = ? AND bs.barber_id = ? AND s.active = 1 AND b.active = 1
             LIMIT 1',
            [$serviceId, $barberId]
        );

        if ($service === null) {
            return [];
        }

        $hours = DB::one(
            'SELECT * FROM working_hours WHERE barber_id = ? AND weekday = ? AND is_working = 1 LIMIT 1',
            [$barberId, $weekday]
        );

        if ($hours === null) {
            return [];
        }

        $duration = (int) $service['duration_minutes'];

        // things that can block a slot, fetched once for the whole day
        $exceptions = DB::all(
            'SELECT * FROM schedule_exceptions
             WHERE barber_id = ? AND exception_date = ? AND is_available = 0',
            [$barberId, $date]
        );

        $dayStartUtc = $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $dayEndUtc = $day->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $bookings = DB::all(
            "SELECT starts_at, ends_at FROM appointments
             WHERE barber_id = ?
               AND status IN ('pending', 'confirmed', 'in_progress')
               AND starts_at < ? AND ends_at > ?",
            [$barberId, $dayEndUtc, $dayStartUtc]
        );

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $opens = $day->setTime(
            (int) substr($hours['start_time'], 0, 2),
            (int) substr($hours['start_time'], 3, 2)
        );
        $closes = $day->setTime(
            (int) substr($hours['end_time'], 0, 2),
            (int) substr($hours['end_time'], 3, 2)
        );

        $lastStart = $closes->modify('-' . $duration . ' minutes');
        $slots = [];

        for ($slotStart = $opens; $slotStart <= $lastStart; $slotStart = $slotStart->modify('+' . self::SLOT_INTERVAL_MINUTES . ' minutes')) {
            $slotEnd = $slotStart->modify('+' . $duration . ' minutes');

            $utcStart = $slotStart->setTimezone(new DateTimeZone('UTC'));
            $utcEnd = $slotEnd->setTimezone(new DateTimeZone('UTC'));
            $utcStartString = $utcStart->format('Y-m-d H:i:s');
            $utcEndString = $utcEnd->format('Y-m-d H:i:s');

            $reason = 'available';

            if ($utcStart <= $now) {
                $reason = 'past';
            } else {
                foreach ($exceptions as $exception) {
                    if ($exception['start_time'] === null
                        || ($slotStart->format('H:i:s') < $exception['end_time']
                            && $slotEnd->format('H:i:s') > $exception['start_time'])) {
                        $reason = 'exception';
                        break;
                    }
                }

                if ($reason === 'available') {
                    foreach ($bookings as $booking) {
                        if ($booking['starts_at'] < $utcEndString && $booking['ends_at'] > $utcStartString) {
                            $reason = 'busy';
                            break;
                        }
                    }
                }
            }

            $slots[] = [
                'time' => $slotStart->format('H:i'),
                'starts_at' => $utcStartString,
                'available' => $reason === 'available',
                'reason' => $reason,
            ];
        }

        return $slots;
    }

    /**
     * Create a booking. Returns the reference and the secret token, or throws
     * when the slot has been taken in the meantime.
     */
    public static function create(array $input): array
    {
        $pdo = DB::getInstance();

        $barberId = (string) $input['barber_id'];
        $serviceId = (string) $input['service_id'];
        $startsAt = (string) $input['starts_at'];   // UTC 'Y-m-d H:i:s'

        $service = DB::one(
            'SELECT s.id, s.name, s.duration_minutes, s.price_pence
             FROM services s
             JOIN barber_services bs ON bs.service_id = s.id
             WHERE s.id = ? AND bs.barber_id = ? AND s.active = 1
             LIMIT 1',
            [$serviceId, $barberId]
        );

        if ($service === null) {
            throw new RuntimeException('That service is not offered by this team member.');
        }

        $duration = (int) $service['duration_minutes'];
        $starts = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
        $ends = $starts->modify('+' . $duration . ' minutes');

        if ($starts <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new RuntimeException('That time is in the past.');
        }

        $startsString = $starts->format('Y-m-d H:i:s');
        $endsString = $ends->format('Y-m-d H:i:s');

        // the slot must still be part of the published schedule
        $date = $starts->setTimezone(new DateTimeZone(SITE_TIMEZONE))->format('Y-m-d');
        $offered = false;
        foreach (self::availableSlots($barberId, $serviceId, $date) as $slot) {
            if ($slot['starts_at'] === $startsString && $slot['available']) {
                $offered = true;
                break;
            }
        }

        if (!$offered) {
            throw new RuntimeException('That slot is no longer available. Please choose another time.');
        }

        $pdo->beginTransaction();

        try {
            $customerId = self::findOrCreateCustomer(
                (string) $input['customer_name'],
                (string) $input['customer_email'],
                (string) $input['customer_phone'],
                !empty($input['marketing_opt_in'])
            );

            // final check inside the transaction, so two people cannot take the same slot
            $clash = DB::one(
                "SELECT id FROM appointments
                 WHERE barber_id = ?
                   AND status IN ('pending', 'confirmed', 'in_progress')
                   AND starts_at < ? AND ends_at > ?
                 LIMIT 1 FOR UPDATE",
                [$barberId, $endsString, $startsString]
            );

            if ($clash !== null) {
                throw new RuntimeException('Sorry, that slot was just taken. Please choose another time.');
            }

            $reference = self::nextReference();
            $token = self::uuid();

            DB::run(
                'INSERT INTO appointments
                   (reference, public_token, customer_id, barber_id, service_id,
                    starts_at, ends_at, status, price_pence_snapshot, service_name_snapshot, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $reference,
                    $token,
                    $customerId,
                    $barberId,
                    $serviceId,
                    $startsString,
                    $endsString,
                    'pending',
                    (int) $service['price_pence'],
                    $service['name'],
                    (string) ($input['notes'] ?? ''),
                ]
            );

            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        return ['reference' => $reference, 'token' => $token];
    }

    /** Look up a booking using the short reference and the secret token. */
    public static function findByReference(string $reference, string $token): ?array
    {
        if ($reference === '' || $token === '') {
            return null;
        }

        return DB::one(
            'SELECT a.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    b.name AS barber_name, s.name AS service_name
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             JOIN barbers b ON b.id = a.barber_id
             JOIN services s ON s.id = a.service_id
             WHERE a.reference = ? AND a.public_token = ?
             LIMIT 1',
            [$reference, $token]
        );
    }

    /**
     * Cancel one of the signed-in customer's own bookings.
     *
     * @return array{ok:bool, message:string}
     */
    public static function cancelForCustomer(string $appointmentId, string $customerId): array
    {
        $row = DB::one(
            'SELECT id, status, (starts_at <= UTC_TIMESTAMP()) AS has_started
             FROM appointments
             WHERE id = ? AND customer_id = ?
             LIMIT 1',
            [$appointmentId, $customerId]
        );

        return self::cancelRow($row, 'appointment');
    }

    /**
     * Cancel a booking from the confirmation link, which carries the private
     * reference and token instead of a login.
     *
     * @return array{ok:bool, message:string}
     */
    public static function cancelByReference(string $reference, string $token): array
    {
        if ($reference === '' || $token === '') {
            return ['ok' => false, 'message' => 'That booking link is not complete.'];
        }

        $row = DB::one(
            'SELECT id, status, (starts_at <= UTC_TIMESTAMP()) AS has_started
             FROM appointments
             WHERE reference = ? AND public_token = ?
             LIMIT 1',
            [$reference, $token]
        );

        return self::cancelRow($row, 'booking');
    }

    /**
     * Shared rules: only a booking that has not started, and is still pending
     * or confirmed, can be cancelled. Completed, no-show and already-cancelled
     * rows are left alone.
     *
     * @param array<string,mixed>|null $row
     * @return array{ok:bool, message:string}
     */
    private static function cancelRow(?array $row, string $noun): array
    {
        if ($row === null) {
            return ['ok' => false, 'message' => 'We could not find that ' . $noun . '.'];
        }

        if ($row['status'] === 'cancelled') {
            return ['ok' => false, 'message' => 'That ' . $noun . ' is already cancelled.'];
        }

        if (!in_array($row['status'], ['pending', 'confirmed'], true)) {
            return [
                'ok' => false,
                'message' => 'That ' . $noun . ' can no longer be cancelled online - please call the salon.',
            ];
        }

        if ((int) $row['has_started'] === 1) {
            return [
                'ok' => false,
                'message' => 'That ' . $noun . ' has already started - please call the salon.',
            ];
        }

        $affected = DB::run(
            "UPDATE appointments
             SET status = 'cancelled', cancelled_at = NOW()
             WHERE id = ? AND status IN ('pending', 'confirmed')",
            [$row['id']]
        );

        if ($affected < 1) {
            return ['ok' => false, 'message' => 'That ' . $noun . ' had already changed - please refresh.'];
        }

        return ['ok' => true, 'message' => 'Your ' . $noun . ' is cancelled. The team has been told.'];
    }

    private static function findOrCreateCustomer(string $name, string $email, string $phone, bool $optIn): string
    {
        $existing = DB::one('SELECT id FROM customers WHERE email = ? LIMIT 1', [$email]);

        if ($existing !== null) {
            DB::run(
                'UPDATE customers SET name = ?, phone = ?, marketing_opt_in = ? WHERE id = ?',
                [$name, $phone, $optIn ? 1 : 0, $existing['id']]
            );

            return (string) $existing['id'];
        }

        $id = self::uuid();
        DB::run(
            'INSERT INTO customers (id, name, email, phone, marketing_opt_in) VALUES (?, ?, ?, ?, ?)',
            [$id, $name, $email, $phone, $optIn ? 1 : 0]
        );

        return $id;
    }

    /** References look like MB-48213, matching the original system. */
    private static function nextReference(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $reference = 'MB-' . random_int(10000, 99999);

            if (DB::one('SELECT id FROM appointments WHERE reference = ? LIMIT 1', [$reference]) === null) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not allocate a booking reference.');
    }

    /** UUID v4, matching the char(36) columns. */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
