-- Water Questions System
-- Dynamic question/answer system for water information

-- Questions table
CREATE TABLE IF NOT EXISTS `bk_water_questions` (
  `question_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('radio', 'checkbox', 'dropdown', 'text', 'textarea') NOT NULL,
  `page_tag` VARCHAR(50) NOT NULL DEFAULT 'water_info',
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `depends_on_question_id` INT(11) NULL,
  `depends_on_answer_value` VARCHAR(255) NULL,
  `help_text` TEXT NULL,
  `terms_link` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`),
  INDEX `idx_page_tag` (`page_tag`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_display_order` (`display_order`),
  INDEX `idx_depends_on` (`depends_on_question_id`),
  FOREIGN KEY (`depends_on_question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question options (for radio, checkbox, dropdown)
CREATE TABLE IF NOT EXISTS `bk_water_question_options` (
  `option_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_id` INT(11) NOT NULL,
  `option_value` VARCHAR(255) NOT NULL,
  `option_text` VARCHAR(255) NOT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`option_id`),
  INDEX `idx_question` (`question_id`),
  INDEX `idx_display_order` (`display_order`),
  FOREIGN KEY (`question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User responses to questions
CREATE TABLE IF NOT EXISTS `bk_water_user_responses` (
  `response_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `question_id` INT(11) NOT NULL,
  `response_value` TEXT NULL,
  `response_text` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`response_id`),
  UNIQUE KEY `unique_user_question` (`user_id`, `question_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_question` (`question_id`),
  INDEX `idx_updated` (`updated_at`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

