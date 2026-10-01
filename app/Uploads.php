<?php
/**
 * Image uploads, currently for team photos.
 *
 * Files land in assets/uploads and are named after the member, so an image can
 * always be traced back to whoever uploaded it. The type is worked out from
 * the file's own contents rather than trusting what the browser claimed, and
 * anything that is not a real JPG, PNG or WebP is refused.
 */

declare(strict_types=1);

final class Uploads
{
    /** The largest photo we accept, in bytes. */
    public const MAX_BYTES = 3 * 1024 * 1024;

    /** Where uploads live, relative to the application root. */
    public const DIRECTORY = 'assets/uploads';

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Store an uploaded team photo and return its path relative to the app.
     *
     * @param array<string, mixed>|null $file one entry from $_FILES
     * @throws RuntimeException when the file is missing, too big or not an image
     */
    public static function storeTeamPhoto(?array $file, string $slug): string
    {
        if ($file === null || !isset($file['error'])) {
            throw new RuntimeException('Choose a photo to upload.');
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Choose a photo to upload.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::errorMessage($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('That upload did not arrive properly. Please try again.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('That file looks empty. Please try another.');
        }

        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('Photos must be smaller than ' . self::maxLabel() . '.');
        }

        $info = @getimagesize($tmp);
        $type = is_array($info) ? (int) ($info[2] ?? 0) : 0;

        if (!isset(self::ALLOWED[$type])) {
            throw new RuntimeException('That was not a JPG, PNG or WebP image.');
        }

        $directory = self::path();
        self::ensureDirectory($directory);

        $name = self::slugify($slug) . '-' . bin2hex(random_bytes(5)) . '.' . self::ALLOWED[$type];
        $target = $directory . '/' . $name;

        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('We could not save that image. Please try again.');
        }

        @chmod($target, 0644);

        return self::DIRECTORY . '/' . $name;
    }

    /**
     * Delete a photo we stored earlier. Links to other sites, and anything
     * outside the uploads directory, are left alone.
     */
    public static function remove(?string $stored): void
    {
        $stored = trim((string) $stored);

        if ($stored === '' || !str_starts_with($stored, self::DIRECTORY . '/')) {
            return;
        }

        $name = basename($stored);

        if ($name === '' || str_contains($name, '..')) {
            return;
        }

        $file = self::path() . '/' . $name;

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** True when the value points at a file this app uploaded. */
    public static function isUploaded(?string $stored): bool
    {
        return str_starts_with(trim((string) $stored), self::DIRECTORY . '/');
    }

    public static function maxLabel(): string
    {
        return (int) (self::MAX_BYTES / 1024 / 1024) . ' MB';
    }

    private static function path(): string
    {
        return dirname(__DIR__) . '/' . self::DIRECTORY;
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The uploads folder is not writable. Check permissions on ' . self::DIRECTORY . '.');
        }
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'team' : mb_substr($value, 0, 60);
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That photo is larger than the server allows. Try a smaller one.',
            UPLOAD_ERR_PARTIAL => 'The upload stopped part way through. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store that file. Please try again.',
            UPLOAD_ERR_EXTENSION => 'That upload was blocked by a server extension.',
            default => 'That upload failed. Please try again.',
        };
    }
}