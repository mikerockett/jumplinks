CREATE TABLE IF NOT EXISTS <table-name> (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source` VARCHAR(512) NOT NULL,
    `destination` VARCHAR(512) NOT NULL,
    `hits` INT UNSIGNED DEFAULT 0,
    `user_created` INT UNSIGNED DEFAULT 0,
    `user_updated` INT UNSIGNED DEFAULT 0,
    `date_start` TIMESTAMP NULL,
    `date_end` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY(id)
) DEFAULT CHARSET=utf8;
