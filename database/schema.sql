CREATE TABLE IF NOT EXISTS `bill_groups` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bill_groups_created_by` (`created_by`),
    CONSTRAINT `fk_bill_groups_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_members` (
    `group_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('owner', 'member') NOT NULL DEFAULT 'member',
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`, `user_id`),
    KEY `idx_group_members_user_id` (`user_id`),
    CONSTRAINT `fk_group_members_group`
        FOREIGN KEY (`group_id`) REFERENCES `bill_groups` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_group_members_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `group_id` INT NOT NULL,
    `paid_by_user_id` INT NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'RUB',
    `split_mode` ENUM('equal', 'custom') NOT NULL DEFAULT 'equal',
    `spent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_expenses_id_group_id` (`id`, `group_id`),
    KEY `idx_expenses_group_spent_at` (`group_id`, `spent_at`),
    KEY `idx_expenses_paid_by` (`paid_by_user_id`),
    KEY `idx_expenses_created_by` (`created_by_user_id`),
    CONSTRAINT `fk_expenses_group`
        FOREIGN KEY (`group_id`) REFERENCES `bill_groups` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_expenses_payer_membership`
        FOREIGN KEY (`group_id`, `paid_by_user_id`)
        REFERENCES `group_members` (`group_id`, `user_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_expenses_creator_membership`
        FOREIGN KEY (`group_id`, `created_by_user_id`)
        REFERENCES `group_members` (`group_id`, `user_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_splits` (
    `expense_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `share_amount` DECIMAL(12, 2) NOT NULL,
    PRIMARY KEY (`expense_id`, `user_id`),
    KEY `idx_expense_splits_group_user` (`group_id`, `user_id`),
    CONSTRAINT `fk_expense_splits_expense`
        FOREIGN KEY (`expense_id`, `group_id`)
        REFERENCES `expenses` (`id`, `group_id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_expense_splits_member`
        FOREIGN KEY (`group_id`, `user_id`)
        REFERENCES `group_members` (`group_id`, `user_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
