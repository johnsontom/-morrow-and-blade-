-- =====================================================================
-- Morrow & Blade - phase 2 migration
-- Accounts (admin / barber / customer), multiple salon branches,
-- barber<->customer chat and AI assistant history.
--
-- Safe to run on a database created from the original schema. Every
-- statement is guarded so re-running the file does not error out.
-- =====================================================================

SET @schema := DATABASE();

-- ------------------------------------------------------------- profiles
-- Dashboard logins. A profile can be an owner/admin or a barber; a barber
-- profile is linked to exactly one row in `barbers`.
ALTER TABLE `profiles`
  ADD COLUMN IF NOT EXISTS `email` varchar(254) DEFAULT NULL AFTER `full_name`,
  ADD COLUMN IF NOT EXISTS `password_hash` varchar(255) NOT NULL DEFAULT '' AFTER `email`,
  ADD COLUMN IF NOT EXISTS `phone` varchar(40) NOT NULL DEFAULT '' AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `barber_id` char(36) DEFAULT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `barber_id`,
  ADD COLUMN IF NOT EXISTS `last_login_at` datetime DEFAULT NULL AFTER `is_active`;

ALTER TABLE `profiles`
  MODIFY COLUMN `role` enum('pending','admin','manager','barber','staff') NOT NULL DEFAULT 'pending';

SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'profiles' AND INDEX_NAME = 'profiles_email_key');
SET @sql := IF(@has = 0, 'ALTER TABLE `profiles` ADD UNIQUE KEY `profiles_email_key` (`email`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'profiles'
    AND CONSTRAINT_NAME = 'profiles_barber_id_fkey');
SET @sql := IF(@has = 0, 'ALTER TABLE `profiles` ADD CONSTRAINT `profiles_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------ customers
-- Customers can still book as a guest; an account simply adds a password
-- so they can sign in to see bookings and chat with their barber.
ALTER TABLE `customers`
  ADD COLUMN IF NOT EXISTS `password_hash` varchar(255) DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `preferred_barber_id` char(36) DEFAULT NULL AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `notes` varchar(1000) NOT NULL DEFAULT '' AFTER `preferred_barber_id`,
  ADD COLUMN IF NOT EXISTS `last_login_at` datetime DEFAULT NULL AFTER `marketing_opt_in`;

SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'customers'
    AND CONSTRAINT_NAME = 'customers_preferred_barber_id_fkey');
SET @sql := IF(@has = 0, 'ALTER TABLE `customers` ADD CONSTRAINT `customers_preferred_barber_id_fkey` FOREIGN KEY (`preferred_barber_id`) REFERENCES `barbers` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --------------------------------------------------------------- salons
CREATE TABLE IF NOT EXISTS `salons` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(150) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL,
  `postcode` varchar(20) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `email` varchar(254) NOT NULL DEFAULT '',
  `opening_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`opening_hours`)),
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `salons_slug_key` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical branches. Used for the closest-salon finder.';

ALTER TABLE `barbers`
  ADD COLUMN IF NOT EXISTS `salon_id` char(36) DEFAULT NULL AFTER `staff_kind`;

SET @has := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'barbers'
    AND CONSTRAINT_NAME = 'barbers_salon_id_fkey');
SET @sql := IF(@has = 0, 'ALTER TABLE `barbers` ADD CONSTRAINT `barbers_salon_id_fkey` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE SET NULL', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------- conversations
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `customer_id` char(36) NOT NULL,
  `barber_id` char(36) NOT NULL,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `last_message_at` datetime DEFAULT NULL,
  `customer_unread` int(11) NOT NULL DEFAULT 0,
  `barber_unread` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_pair_key` (`customer_id`,`barber_id`),
  KEY `conversations_barber_idx` (`barber_id`,`last_message_at`),
  CONSTRAINT `conversations_customer_id_fkey` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_barber_id_fkey` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One thread per customer/barber pair.';

CREATE TABLE IF NOT EXISTS `messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) NOT NULL,
  `sender_type` enum('customer','barber') NOT NULL,
  `sender_customer_id` char(36) DEFAULT NULL,
  `sender_profile_id` char(36) DEFAULT NULL,
  `body` text NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `messages_conversation_idx` (`conversation_id`,`id`),
  CONSTRAINT `messages_conversation_id_fkey` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_customer_id_fkey` FOREIGN KEY (`sender_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_sender_profile_id_fkey` FOREIGN KEY (`sender_profile_id`) REFERENCES `profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat messages. Body is plain text, escaped on output.';

-- ------------------------------------------------------------ ai history
CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `customer_id` char(36) DEFAULT NULL,
  `session_key` varchar(64) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ai_conversations_session_idx` (`session_key`,`updated_at`),
  CONSTRAINT `ai_conversations_customer_id_fkey` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assistant threads, keyed by browser session when signed out.';

CREATE TABLE IF NOT EXISTS `ai_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ai_messages_conversation_idx` (`conversation_id`,`id`),
  CONSTRAINT `ai_messages_conversation_id_fkey` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assistant transcript.';
