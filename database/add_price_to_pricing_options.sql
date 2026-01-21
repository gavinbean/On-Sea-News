-- Add price field to pricing options table
ALTER TABLE `bk_business_pricing_options`
ADD COLUMN `price` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `description`,
ADD INDEX `idx_price` (`price`);

-- Add allowed_adverts field to pricing options table
ALTER TABLE `bk_business_pricing_options`
ADD COLUMN `allowed_adverts` INT(11) NOT NULL DEFAULT 0 AFTER `price`,
ADD INDEX `idx_allowed_adverts` (`allowed_adverts`);

-- Update existing options with default prices (can be changed in admin)
UPDATE `bk_business_pricing_options` SET `price` = 0.00 WHERE `option_slug` = 'free';
UPDATE `bk_business_pricing_options` SET `price` = 0.00 WHERE `option_slug` = 'basic';
UPDATE `bk_business_pricing_options` SET `price` = 0.00 WHERE `option_slug` = 'timed';
UPDATE `bk_business_pricing_options` SET `price` = 0.00 WHERE `option_slug` = 'events';

-- Set default allowed adverts (can be changed in admin)
UPDATE `bk_business_pricing_options` SET `allowed_adverts` = 0 WHERE `option_slug` = 'free';
UPDATE `bk_business_pricing_options` SET `allowed_adverts` = 1 WHERE `option_slug` = 'basic';
UPDATE `bk_business_pricing_options` SET `allowed_adverts` = 5 WHERE `option_slug` = 'timed';
UPDATE `bk_business_pricing_options` SET `allowed_adverts` = 10 WHERE `option_slug` = 'events';
