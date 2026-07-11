ALTER TABLE `users`
    ADD COLUMN `firebase_uid` VARCHAR(128) NULL DEFAULT NULL AFTER `password`,
    ADD COLUMN `auth_provider` VARCHAR(32) NULL DEFAULT NULL AFTER `firebase_uid`,
    ADD UNIQUE KEY `idx_users_firebase_uid` (`firebase_uid`);

ALTER TABLE `admin`
    ADD COLUMN `firebase_uid` VARCHAR(128) NULL DEFAULT NULL AFTER `password`,
    ADD COLUMN `auth_provider` VARCHAR(32) NULL DEFAULT NULL AFTER `firebase_uid`,
    ADD UNIQUE KEY `idx_admin_firebase_uid` (`firebase_uid`);
