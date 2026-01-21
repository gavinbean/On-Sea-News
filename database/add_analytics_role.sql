-- Add ANALYTICS role
INSERT INTO `bk_roles` (`role_name`, `role_description`) VALUES
('ANALYTICS', 'Can view water analytics and reports')
ON DUPLICATE KEY UPDATE role_name=role_name;



