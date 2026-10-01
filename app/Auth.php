<?php
/**
 * Session handling and sign-in for both dashboard users (owner + barbers)
 * and customers.
 *
 * Two separate session keys are kept so someone can be signed in as a
 * customer in one browser tab and as a barber in another without clashing.
 */

declare(strict_types=1);

final class Auth
{
    public const PROFILE_KEY = 'auth_profile_id';
    public const CUSTOMER_KEY = 'auth_customer_id';
    public const CSRF_KEY = 'auth_csrf';

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Keep session files inside the app so it works on shared hosting,
            // where the system temp directory is often not writable.
            $sessionDir = dirname(__DIR__) . '/storage/sessions';

            if (!is_dir($sessionDir)) {
                @mkdir($sessionDir, 0775, true);
            }

            if (is_dir($sessionDir) && is_writable($sessionDir)) {
                session_save_path($sessionDir);
            }

            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            ]);
            session_start();
        }

        self::$booted = true;
    }

    // ------------------------------------------------------------- profiles

    public static function profile(): ?array
    {
        self::boot();
        $id = $_SESSION[self::PROFILE_KEY] ?? null;

        if (!is_string($id) || $id === '') {
            return null;
        }

        $row = AccountDAO::findProfileById($id);

        if ($row === null || (int) $row['is_active'] !== 1) {
            unset($_SESSION[self::PROFILE_KEY]);

            return null;
        }

        return $row;
    }

    public static function isAdmin(): bool
    {
        $profile = self::profile();

        return $profile !== null && in_array($profile['role'], ['admin', 'manager'], true);
    }

    public static function isBarber(): bool
    {
        $profile = self::profile();

        return $profile !== null && $profile['role'] === 'barber' && $profile['barber_id'] !== null;
    }

    /** Sign a dashboard user in. Returns false when the password is wrong. */
    public static function attemptProfile(string $email, string $password): bool
    {
        $row = AccountDAO::findProfileByEmail($email);

        if ($row === null || (int) $row['is_active'] !== 1) {
            return false;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            AccountDAO::updateProfilePassword((string) $row['id'], $password);
        }

        self::boot();
        session_regenerate_id(true);
        $_SESSION[self::PROFILE_KEY] = $row['id'];
        AccountDAO::touchProfileLogin((string) $row['id']);

        return true;
    }

    // ------------------------------------------------------------ customers

    public static function customer(): ?array
    {
        self::boot();
        $id = $_SESSION[self::CUSTOMER_KEY] ?? null;

        if (!is_string($id) || $id === '') {
            return null;
        }

        $row = AccountDAO::findCustomerById($id);

        if ($row === null) {
            unset($_SESSION[self::CUSTOMER_KEY]);

            return null;
        }

        return $row;
    }

    public static function attemptCustomer(string $email, string $password): bool
    {
        $row = AccountDAO::findCustomerByEmail($email);

        if ($row === null || empty($row['password_hash'])) {
            return false;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        self::boot();
        session_regenerate_id(true);
        $_SESSION[self::CUSTOMER_KEY] = $row['id'];
        AccountDAO::touchCustomerLogin((string) $row['id']);

        return true;
    }

    public static function registerCustomer(string $name, string $email, string $phone, string $password): ?string
    {
        $existing = AccountDAO::findCustomerByEmail($email);

        if ($existing !== null) {
            if (!empty($existing['password_hash'])) {
                return null; // already has an account
            }

            // Guest booking history exists - attach an account to it.
            AccountDAO::setCustomerPassword((string) $existing['id'], $password);
            $id = (string) $existing['id'];
        } else {
            $id = AccountDAO::createCustomer($name, $email, $phone, $password);
        }

        self::boot();
        session_regenerate_id(true);
        $_SESSION[self::CUSTOMER_KEY] = $id;

        return $id;
    }

    // -------------------------------------------------------------- session

    public static function logout(): void
    {
        self::boot();
        unset($_SESSION[self::PROFILE_KEY]);
        session_regenerate_id(true);
    }

    public static function logoutCustomer(): void
    {
        self::boot();
        unset($_SESSION[self::CUSTOMER_KEY]);
        session_regenerate_id(true);
    }

    /** Send the visitor to the login page unless they are signed in. */
    public static function requireProfile(): array
    {
        $profile = self::profile();

        if ($profile === null) {
            $target = url('barber/login.php');
            header('Location: ' . $target . '?next=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
            exit;
        }

        return $profile;
    }

    public static function requireCustomer(): array
    {
        $customer = self::customer();

        if ($customer === null) {
            header('Location: ' . url('login.php') . '?next=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
            exit;
        }

        return $customer;
    }

    // ----------------------------------------------------------------- csrf

    public static function csrfToken(): string
    {
        self::boot();

        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CSRF_KEY];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::csrfToken()) . '">';
    }

    public static function checkCsrf(?string $token): bool
    {
        self::boot();
        $expected = (string) ($_SESSION[self::CSRF_KEY] ?? '');

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    // --------------------------------------------------------------- flashes

    public static function flash(string $type, string $message): void
    {
        self::boot();
        $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlashes(): array
    {
        self::boot();
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);

        return is_array($flashes) ? $flashes : [];
    }
}
