-- Create water delivery companies table
CREATE TABLE IF NOT EXISTS `bk_water_delivery_companies` (
  `company_id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(200) NOT NULL UNIQUE,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  INDEX `idx_name` (`company_name`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create water deliveries table
CREATE TABLE IF NOT EXISTS `bk_water_deliveries` (
  `delivery_id` INT(11) NOT NULL AUTO_INCREMENT,
  `date_ordered` DATE NOT NULL,
  `date_delivered` DATE NOT NULL,
  `company_id` INT(11) NULL COMMENT 'NULL if company_name_other is used',
  `company_name_other` VARCHAR(200) NULL COMMENT 'Used when company_id is NULL (Other option)',
  `vehicle_registration` VARCHAR(50) NOT NULL,
  `litres_delivered` DECIMAL(10, 2) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `logged_by_user_id` INT(11) NOT NULL COMMENT 'Admin user who logged this delivery',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`delivery_id`),
  FOREIGN KEY (`company_id`) REFERENCES `bk_water_delivery_companies`(`company_id`) ON DELETE SET NULL,
  FOREIGN KEY (`logged_by_user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_date_ordered` (`date_ordered`),
  INDEX `idx_date_delivered` (`date_delivered`),
  INDEX `idx_company` (`company_id`),
  INDEX `idx_logged_by` (`logged_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default companies
INSERT INTO `bk_water_delivery_companies` (`company_name`) VALUES
('Municipality'),
('Private Water Supplier A'),
('Private Water Supplier B')
ON DUPLICATE KEY UPDATE company_name=company_name;
