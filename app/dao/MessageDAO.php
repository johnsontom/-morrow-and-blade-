<?php
/**
 * Chat between a customer and a barber.
 *
 * One conversation per customer/barber pair. Unread counts live on the
 * conversation row so the inbox does not need a subquery per thread.
 */

declare(strict_types=1);

final class MessageDAO
{
    public const MAX_LENGTH = 2000;

    public static function conversationForPair(string $customerId, string $barberId): ?array
    {
        return DB::one(
            'SELECT * FROM conversations WHERE customer_id = ? AND barber_id = ? LIMIT 1',
            [$customerId, $barberId]
        );
    }

    public static function openConversation(string $customerId, string $barberId, string $subject = ''): array
    {
        $existing = self::conversationForPair($customerId, $barberId);

        if ($existing !== null) {
            return $existing;
        }

        $id = AccountDAO::uuid();

        DB::run(
            'INSERT IGNORE INTO conversations (id, customer_id, barber_id, subject) VALUES (?, ?, ?, ?)',
            [$id, $customerId, $barberId, mb_substr($subject, 0, 200)]
        );

        return self::conversationForPair($customerId, $barberId) ?? [
            'id' => $id,
            'customer_id' => $customerId,
            'barber_id' => $barberId,
            'subject' => $subject,
        ];
    }

    public static function byId(string $id): ?array
    {
        return DB::one(
            'SELECT c.*, cu.name AS customer_name, cu.email AS customer_email,
                    b.name AS barber_name, b.slug AS barber_slug, b.photo_url AS barber_photo
             FROM conversations c
             JOIN customers cu ON cu.id = c.customer_id
             JOIN barbers b ON b.id = c.barber_id
             WHERE c.id = ? LIMIT 1',
            [$id]
        );
    }

    /** Load a thread only if the given customer owns it. */
    public static function ownedByCustomer(string $id, string $customerId): ?array
    {
        $row = self::byId($id);

        return $row !== null && $row['customer_id'] === $customerId ? $row : null;
    }

    /** Load a thread only if the given barber is a participant. */
    public static function ownedByBarber(string $id, string $barberId): ?array
    {
        $row = self::byId($id);

        return $row !== null && $row['barber_id'] === $barberId ? $row : null;
    }

    public static function messages(string $conversationId, int $afterId = 0): array
    {
        return DB::all(
            'SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 500',
            [$conversationId, $afterId]
        );
    }

    public static function send(
        string $conversationId,
        string $senderType,
        ?string $customerId,
        ?string $profileId,
        string $body
    ): ?int {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        $body = mb_substr($body, 0, self::MAX_LENGTH);
        $conversation = self::byId($conversationId);

        if ($conversation === null) {
            return null;
        }

        DB::run(
            'INSERT INTO messages (conversation_id, sender_type, sender_customer_id, sender_profile_id, body)
             VALUES (?, ?, ?, ?, ?)',
            [$conversationId, $senderType, $customerId, $profileId, $body]
        );

        $messageId = (int) DB::getInstance()->lastInsertId();

        if ($senderType === 'customer') {
            DB::run(
                'UPDATE conversations
                 SET last_message_at = NOW(), barber_unread = barber_unread + 1, status = \'open\',
                     subject = IF(subject = \'\', ?, subject)
                 WHERE id = ?',
                [mb_substr($body, 0, 80), $conversationId]
            );
        } else {
            DB::run(
                'UPDATE conversations
                 SET last_message_at = NOW(), customer_unread = customer_unread + 1, status = \'open\'
                 WHERE id = ?',
                [$conversationId]
            );
        }

        return $messageId;
    }

    public static function conversationsForCustomer(string $customerId): array
    {
        return DB::all(
            'SELECT c.*, b.name AS barber_name, b.slug AS barber_slug, b.photo_url AS barber_photo,
                    b.role AS barber_role,
                    (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender
             FROM conversations c
             JOIN barbers b ON b.id = c.barber_id
             WHERE c.customer_id = ?
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC',
            [$customerId]
        );
    }

    public static function conversationsForBarber(string $barberId): array
    {
        return DB::all(
            'SELECT c.*, cu.name AS customer_name, cu.email AS customer_email,
                    (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender
             FROM conversations c
             JOIN customers cu ON cu.id = c.customer_id
             WHERE c.barber_id = ?
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC',
            [$barberId]
        );
    }

    /** Zero the unread counter for whoever just opened the thread. */
    public static function markRead(string $conversationId, string $viewer): void
    {
        $column = $viewer === 'barber' ? 'barber_unread' : 'customer_unread';
        DB::run("UPDATE conversations SET $column = 0 WHERE id = ?", [$conversationId]);
        DB::run(
            "UPDATE messages SET read_at = NOW()
             WHERE conversation_id = ? AND read_at IS NULL AND sender_type <> ?",
            [$conversationId, $viewer === 'barber' ? 'barber' : 'customer']
        );
    }

    public static function unreadForCustomer(string $customerId): int
    {
        return (int) (DB::one(
            'SELECT COALESCE(SUM(customer_unread), 0) AS total FROM conversations WHERE customer_id = ?',
            [$customerId]
        )['total'] ?? 0);
    }
}
