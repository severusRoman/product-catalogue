-- Shelfwise Product Catalogue: database schema
-- Import into an EMPTY database (phpMyAdmin > Import, or: mysql -u user -p dbname < schema.sql)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Registered accounts. Passwords are stored ONLY as password_hash() output.
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(60)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed list of product categories (used by the filter and the product form).
CREATE TABLE categories (
  id   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main entity for CRUD. Every product belongs to the user who created it.
CREATE TABLE products (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,
  image_path  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_user (user_id),
  KEY idx_products_category (category_id),
  KEY idx_products_name (name),
  KEY idx_products_price (price),
  CONSTRAINT fk_products_user     FOREIGN KEY (user_id)     REFERENCES users (id)      ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
  CONSTRAINT chk_products_price  CHECK (price > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts, used to slow down password guessing.
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(190) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_lookup (email, ip_address, attempted_at),
  KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name) VALUES
  ('Electronics'),
  ('Fashion'),
  ('Home & Kitchen'),
  ('Books & Stationery'),
  ('Sports & Outdoors'),
  ('Beauty & Health'),
  ('Toys & Games'),
  ('Groceries');
