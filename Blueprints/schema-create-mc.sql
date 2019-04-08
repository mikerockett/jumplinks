CREATE TABLE IF NOT EXISTS <table-name>_mc (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collection_name` VARCHAR(255) NOT NULL,
    `collection_mappings` LONGTEXT NOT NULL,
    `user_created` INT UNSIGNED DEFAULT 0,
    `user_updated` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY(collection_name)
) DEFAULT CHARSET=utf8;
