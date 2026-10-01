<?php
/**
 * Reads and writes for the two account tables: `profiles` (owner + staff)
 * and `customers` (the public).
 */

declare(strict_types=1);

final class AccountDAO
{
    // ------------------------------------------------------------- profiles

    public static function findProfileByEmail(string $email): ?array
    {
        return DB::one(
            'SELECT * FROM profiles WHERE email = ? LIMIT 1',
            [strtolower(trim($email))]
        );
    }

    public static function findProfileById(string $id): ?array
    {
        return DB::one('SELECT * FROM profiles WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findProfileForBarber(string $barberId): ?array
    {
        return DB::one(
            "SELECT * FROM profiles WHERE barber_id = ? AND role = 'barber' LIMIT 1",
            [$barberId]
        );
    }

    public static function updateProfilePassword(string $id, string $plain): void
    {
        DB::run(
            'UPDATE profiles SET password_hash = ? WHERE id = ?',
            [password_hash($plain, PASSWORD_DEFAULT), $id]
        );
    }

    public static function touchProfileLogin(string $id): void
    {
        DB::run('UPDATE profiles SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function createProfile(
        string $name,
        string $email,
        string $password,
        string $role = 'staff',
        ?string $barberId = null
    ): string {
        $id = self::uuid();

        DB::run(
            'INSERT INTO profiles (id, full_name, email, password_hash, role, barber_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$id, $name, strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role, $barberId]
        );

        return $id;
    }

    public static function allProfiles(): array
    {
        return DB::all(
            'SELECT p.*, b.name AS barber_name, b.slug AS barber_slug
             FROM profiles p
             LEFT JOIN barbers b ON b.id = p.barber_id
             ORDER BY FIELD(p.role, \'admin\', \'manager\', \'barber\', \'staff\', \'pending\'), p.full_name'
        );
    }

    public static function setProfileActive(string $id, bool $active): void
    {
        DB::run('UPDATE profiles SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    // ------------------------------------------------------------ customers

    public static function findCustomerByEmail(string $email): ?array
    {
        return DB::one(
            'SELECT * FROM customers WHERE email = ? LIMIT 1',
            [strtolower(trim($email))]
        );
    }

    public static function findCustomerById(string $id): ?array
    {
        return DB::one('SELECT * FROM customers WHERE id = ? LIMIT 1', [$id]);
    }

    public static function createCustomer(string $name, string $email, string $phone, ?string $password): string
    {
        $id = self::uuid();

        DB::run(
            'INSERT INTO customers (id, name, email, phone, password_hash)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $name, strtolower(trim($email)), $phone, $password === null ? null : password_hash($password, PASSWORD_DEFAULT)]
        );

        return $id;
    }

    public static function setCustomerPassword(string $id, string $password): void
    {
        DB::run(
            'UPDATE customers SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    public static function touchCustomerLogin(string $id): void
    {
        DB::run('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function updateCustomerProfile(string $id, string $name, string $phone, ?string $preferredBarberId): void
    {
        DB::run(
            'UPDATE customers SET name = ?, phone = ?, preferred_barber_id = ? WHERE id = ?',
            [$name, $phone, $preferredBarberId, $id]
        );
    }

    /** Find or create the customer record used by the public booking form. */
    public static function upsertGuest(string $name, string $email, string $phone): string
    {
        $existing = self::findCustomerByEmail($email);

        if ($existing !== null) {
            DB::run('UPDATE customers SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $existing['id']]);

            return (string) $existing['id'];
        }

        return self::createCustomer($name, $email, $phone, null);
    }

    // ---------------------------------------------------------------- misc

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
