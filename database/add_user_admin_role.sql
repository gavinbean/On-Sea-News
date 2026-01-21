-- Add USER_ADMIN role
-- This role allows access to Manage Users page but cannot access ROLES and DELETE buttons

INSERT INTO `bk_roles` (`role_name`, `role_description`) VALUES
('USER_ADMIN', 'User administrator - can manage users but cannot modify roles or delete users')
ON DUPLICATE KEY UPDATE role_name=role_name;
