CREATE TABLE <table-name>_nf (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_uri` VARCHAR(255) NOT NULL,
    `referrer` VARCHAR(255) DEFAULT '',
    `user_agent` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP,
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8;
