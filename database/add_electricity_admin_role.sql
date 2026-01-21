-- Add ELECTRICITY_ADMIN role
-- This role allows access to manage electricity issues

INSERT INTO `bk_roles` (`role_name`, `role_description`) VALUES
('ELECTRICITY_ADMIN', 'Electricity administrator - can manage electricity issues and updates')
ON DUPLICATE KEY UPDATE role_name=role_name;
