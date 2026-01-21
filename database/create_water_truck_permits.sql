-- Create water truck permits table
CREATE TABLE IF NOT EXISTS `bk_water_truck_permits` (
  `permit_id` INT(11) NOT NULL AUTO_INCREMENT,
  `date_captured` DATE NOT NULL,
  `registration_number` VARCHAR(50) NOT NULL,
  `permit_number` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`permit_id`),
  INDEX `idx_date_captured` (`date_captured`),
  INDEX `idx_registration` (`registration_number`),
  INDEX `idx_permit_number` (`permit_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
