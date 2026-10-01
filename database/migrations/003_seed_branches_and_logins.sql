-- =====================================================================
-- Morrow & Blade - phase 2 seed
-- Three London branches, an owner login and one login per team member.
--
-- Demo credentials (change before going live):
--   Owner    admin@morrowandblade.co.uk   Admin@123
--   Barbers  <slug>@morrowandblade.co.uk  Barber@123
-- =====================================================================

-- ------------------------------------------------------------- branches
INSERT INTO `salons`
  (`id`,`slug`,`name`,`address_line_1`,`address_line_2`,`city`,`postcode`,`latitude`,`longitude`,`phone`,`email`,`opening_hours`,`is_primary`,`active`,`display_order`)
VALUES
  ('a1000000-0000-4000-8000-000000000001','soho','Morrow & Blade Soho','18 Mercer Street','','London','WC2H 9QJ',51.513600,-0.129100,'+44 20 7946 0188','soho@morrowandblade.co.uk','[{"day":"Monday","shortDay":"Mon","hours":"09:00 - 19:00","isOpen":true},{"day":"Tuesday","shortDay":"Tue","hours":"09:00 - 20:00","isOpen":true},{"day":"Wednesday","shortDay":"Wed","hours":"09:00 - 20:00","isOpen":true},{"day":"Thursday","shortDay":"Thu","hours":"09:00 - 20:00","isOpen":true},{"day":"Friday","shortDay":"Fri","hours":"09:00 - 20:00","isOpen":true},{"day":"Saturday","shortDay":"Sat","hours":"09:00 - 18:00","isOpen":true},{"day":"Sunday","shortDay":"Sun","hours":"Closed","isOpen":false}]',1,1,1),
  ('a1000000-0000-4000-8000-000000000002','shoreditch','Morrow & Blade Shoreditch','42 Redchurch Street','','London','E2 7DP',51.524700,-0.075500,'+44 20 7946 0241','shoreditch@morrowandblade.co.uk','[{"day":"Monday","shortDay":"Mon","hours":"10:00 - 19:00","isOpen":true},{"day":"Tuesday","shortDay":"Tue","hours":"10:00 - 20:00","isOpen":true},{"day":"Wednesday","shortDay":"Wed","hours":"10:00 - 20:00","isOpen":true},{"day":"Thursday","shortDay":"Thu","hours":"10:00 - 21:00","isOpen":true},{"day":"Friday","shortDay":"Fri","hours":"10:00 - 21:00","isOpen":true},{"day":"Saturday","shortDay":"Sat","hours":"09:00 - 19:00","isOpen":true},{"day":"Sunday","shortDay":"Sun","hours":"11:00 - 17:00","isOpen":true}]',0,1,2),
  ('a1000000-0000-4000-8000-000000000003','canary-wharf','Morrow & Blade Canary Wharf','12 Cabot Square','','London','E14 4QQ',51.505100,-0.023300,'+44 20 7946 0319','canarywharf@morrowandblade.co.uk','[{"day":"Monday","shortDay":"Mon","hours":"08:00 - 18:00","isOpen":true},{"day":"Tuesday","shortDay":"Tue","hours":"08:00 - 18:00","isOpen":true},{"day":"Wednesday","shortDay":"Wed","hours":"08:00 - 18:00","isOpen":true},{"day":"Thursday","shortDay":"Thu","hours":"08:00 - 19:00","isOpen":true},{"day":"Friday","shortDay":"Fri","hours":"08:00 - 19:00","isOpen":true},{"day":"Saturday","shortDay":"Sat","hours":"09:00 - 16:00","isOpen":true},{"day":"Sunday","shortDay":"Sun","hours":"Closed","isOpen":false}]',0,1,3)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address_line_1` = VALUES(`address_line_1`), `city` = VALUES(`city`), `postcode` = VALUES(`postcode`), `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`), `phone` = VALUES(`phone`), `email` = VALUES(`email`), `opening_hours` = VALUES(`opening_hours`), `is_primary` = VALUES(`is_primary`), `active` = VALUES(`active`), `display_order` = VALUES(`display_order`);

-- Put every team member in a branch.
UPDATE `barbers` SET `salon_id` = 'a1000000-0000-4000-8000-000000000001' WHERE `slug` IN ('jay-morrow','mike-ellis');
UPDATE `barbers` SET `salon_id` = 'a1000000-0000-4000-8000-000000000002' WHERE `slug` IN ('david-chen','jordan-reed');
UPDATE `barbers` SET `salon_id` = 'a1000000-0000-4000-8000-000000000003' WHERE `slug` IN ('amara-diallo','lena-petrova');

-- ---------------------------------------------------------------- owner
INSERT INTO `profiles` (`id`,`full_name`,`email`,`password_hash`,`phone`,`role`,`barber_id`,`is_active`)
VALUES (uuid(), 'Salon Owner', 'admin@morrowandblade.co.uk', '$2y$10$JLNNY5DP5XUMX3bvxmhB/Ok58MX88mzTJpc05aBPDqU0FcGgIYBUC', '+44 20 7946 0188', 'admin', NULL, 1)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `password_hash` = VALUES(`password_hash`), `role` = VALUES(`role`), `is_active` = 1;

-- --------------------------------------------------------- barber logins
INSERT INTO `profiles` (`id`,`full_name`,`email`,`password_hash`,`role`,`barber_id`,`is_active`)
SELECT uuid(), b.`name`, CONCAT(b.`slug`, '@morrowandblade.co.uk'),
       '$2y$10$PI.I8TJ7r9cwnp3Sq2T8G.Ox/0hypysjt2qMEILiEop9GJ4w.XedK',
       'barber', b.`id`, 1
FROM `barbers` b
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`), `barber_id` = VALUES(`barber_id`), `role` = 'barber', `is_active` = 1;
