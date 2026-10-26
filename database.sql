-- YouthCo. Shop | AI Inventory Management System
-- Import this in phpMyAdmin (Import tab). Follows the ERD.
CREATE DATABASE IF NOT EXISTS youthco_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE youthco_db;

CREATE TABLE IF NOT EXISTS roles (
  role_id   INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  role_id       INT NOT NULL,
  last_name     VARCHAR(50) NOT NULL,
  first_name    VARCHAR(50) NOT NULL,
  middle_name   VARCHAR(50) NULL,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  category_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  product_id          INT AUTO_INCREMENT PRIMARY KEY,
  owner_id            INT NOT NULL,            -- each account has its own inventory
  category_id         INT NOT NULL,
  product_name        VARCHAR(100) NOT NULL,
  unit_price          DECIMAL(10,2) NOT NULL,
  stock_quantity      INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 10,
  high_stock_level    INT NOT NULL DEFAULT 60,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
  sale_id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  sale_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
  sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id      INT NOT NULL,
  product_id   INT NOT NULL,
  quantity     INT NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  subtotal     DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
  movement_id     INT AUTO_INCREMENT PRIMARY KEY,
  product_id      INT NOT NULL,
  user_id         INT NULL,
  sale_id         INT NULL,
  movement_type   ENUM('initial','stock_in','stock_out','adjustment') NOT NULL,
  quantity_change INT NOT NULL,
  remarks         VARCHAR(255) NULL,
  movement_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  report_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  report_type   ENUM('inventory','sales','low_stock','high_stock','best_seller') NOT NULL,
  date_from     DATE NULL,
  date_to       DATE NULL,
  export_format ENUM('preview','pdf','csv') NOT NULL DEFAULT 'preview',
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_recommendations (
  recommendation_id    INT AUTO_INCREMENT PRIMARY KEY,
  product_id           INT NOT NULL,
  recommendation_type  ENUM('restock','overstock','fast_moving','slow_moving') NOT NULL,
  priority             ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  message              VARCHAR(500) NOT NULL,
  avg_daily_sales      DECIMAL(8,2) NOT NULL DEFAULT 0,
  suggested_order_qty  INT NOT NULL DEFAULT 0,
  generated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (role_name) VALUES ('Administrator'), ('Staff');
INSERT IGNORE INTO categories (category_name) VALUES
 ('Beverages'),('Instant Noodles'),('Snacks'),('Canned Goods'),
 ('Coffee'),('Milk'),('Personal Care'),('Baby Products');
