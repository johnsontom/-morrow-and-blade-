<?php
/**
 * PDO connection, created once and shared.
 *
 * Same singleton shape as the other projects in htdocs, but with exceptions
 * enabled so a broken query fails loudly instead of silently returning false.
 */

declare(strict_types=1);

final class DB
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $error) {
                http_response_code(500);
                exit('Database connection failed: ' . htmlspecialchars($error->getMessage()));
            }
        }

        return self::$instance;
    }

    /** Run a prepared statement and return every row. */
    public static function all(string $sql, array $params = []): array
    {
        $statement = self::getInstance()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** Run a prepared statement and return the first row, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $statement = self::getInstance()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** Run a write statement and return the affected row count. */
    public static function run(string $sql, array $params = []): int
    {
        $statement = self::getInstance()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }
}