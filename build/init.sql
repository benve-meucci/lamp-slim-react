CREATE TABLE `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_name` VARCHAR(100) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` INT UNSIGNED NOT NULL,
  `type` ENUM('deposit', 'withdrawal') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `balance_after` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_transactions_account_created` (`account_id`, `created_at`),
  CONSTRAINT `fk_transactions_account`
    FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `chk_transactions_amount_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts` (`id`, `owner_name`, `currency`) VALUES
(1, 'Conto laboratorio', 'EUR');

INSERT INTO `transactions` (`account_id`, `type`, `amount`, `description`, `balance_after`) VALUES
(1, 'deposit', 1000.00, 'Saldo iniziale', 1000.00),
(1, 'withdrawal', 125.50, 'Acquisto materiale', 874.50),
(1, 'deposit', 250.00, 'Versamento contanti', 1124.50);
