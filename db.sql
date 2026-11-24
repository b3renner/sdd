CREATE DATABASE IF NOT EXISTS `sdd_login`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `sdd_login`;

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uma linha por e-mail (usuário) que já tentou logar.
-- Controla tentativas consecutivas e janela de bloqueio.
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `email`           VARCHAR(255) NOT NULL PRIMARY KEY,
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `blocked_until`   DATETIME NULL,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
