-- ============================================================
-- LIVE SERVER SEED RESTORATION
-- Run this AFTER importing live-schema.sql into the empty DB.
-- DB: oqtkzvov_suserp
--
-- Recovery admin password: Password123!
-- Change it immediately after first login via /ops/user-control
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) INSTALLED PLUGINS (all active)
TRUNCATE TABLE installed_plugins;

INSERT INTO installed_plugins (name, version, type, status, installed_at) VALUES
('Base',              '3.0.0', 'engine',   'active', NOW()),
('ACL',               '1.0.0', 'engine',   'active', NOW()),
('Organization',      '1.0.0', 'engine',   'active', NOW()),
('Audit',             '1.0.0', 'engine',   'active', NOW()),
('Bus',               '1.0.0', 'engine',   'active', NOW()),
('QRCode',            '1.0.0', 'engine',   'active', NOW()),
('AdminTools',        '1.0.0', 'engine',   'active', NOW()),
('Products',          '1.0.0', 'business', 'active', NOW()),
('Workflow',          '1.0.0', 'business', 'active', NOW()),
('Supply',            '1.0.0', 'business', 'active', NOW()),
('Coverage',          '1.0.0', 'business', 'active', NOW()),
('Machines',          '1.0.0', 'business', 'active', NOW()),
('PartMachineMap',    '1.0.0', 'business', 'active', NOW()),
('PreOrders',         '1.0.0', 'business', 'active', NOW()),
('DailyOrders',       '1.0.0', 'business', 'active', NOW()),
('ProductionPlans',   '1.0.0', 'business', 'active', NOW()),
('ProductionEntries', '1.0.0', 'business', 'active', NOW()),
('ProductionQueue',   '1.0.0', 'business', 'active', NOW()),
('Ledger',            '1.0.0', 'engine',   'active', NOW()),
('MaterialManagement','1.0.0', 'business', 'active', NOW()),
('QCPlans',           '1.0.0', 'business', 'active', NOW()),
('QCEntries',         '1.0.0', 'business', 'active', NOW()),
('DispatchEntries',   '1.0.0', 'business', 'active', NOW()),
('Staff',             '1.0.0', 'business', 'active', NOW()),
('Attendance',        '1.0.0', 'business', 'active', NOW()),
('Customers',         '1.0.0', 'business', 'active', NOW()),
('Timecards',         '1.0.0', 'business', 'active', NOW()),
('Tasks',             '1.0.0', 'business', 'active', NOW()),
('Payroll',           '1.0.0', 'business', 'active', NOW()),
('Sales',             '1.0.0', 'business', 'active', NOW()),
('Schedules',         '1.0.0', 'business', 'active', NOW()),
('Expenses',          '1.0.0', 'business', 'active', NOW()),
('Leave',             '1.0.0', 'business', 'active', NOW()),
('Notices',           '1.0.0', 'business', 'active', NOW()),
('AssemblyPlans',     '1.0.0', 'business', 'active', NOW()),
('AssemblyEntries',   '1.0.0', 'business', 'active', NOW());

-- 2) USERS
-- Recovery password for admin: Password123!
-- Change immediately after login via /ops/user-control
TRUNCATE TABLE users;

INSERT INTO users
  (id, email, display_name, username, department,
   password_hash, auth_session_version,
   role, role_tier, authority_role, account_type,
   account_status, verification_status, security_status,
   twofa_enabled, twofa_secret,
   created_at, updated_at)
VALUES
  (1,
   'lazydeepak@gmail.com',
   'Platform Admin',
   'lazydeepak',
   NULL,
   '$2y$12$8p2d9QiPdf6FQNX1aHW0h.1Btm5S4G/SNN0yHVT.DQsjtLLOJKyHy',
   1,
   'PLATFORM ADMIN', 'admin', 'platform_admin', 'platform_admin',
   'active', 'ready', 'standard',
   0, NULL,
   NOW(), NOW());

-- 3) USER DASHBOARD ASSIGNMENTS (admin)
DELETE FROM user_dashboard_assignments WHERE user_id = 1;

INSERT INTO user_dashboard_assignments
  (user_id, dashboard_type, default_app, default_landing_page,
   assigned_apps, dashboard_mode, default_app_mode, landing_mode,
   account_class, updated_by, created_at, updated_at)
VALUES
  (1, 'platform_admin', 'platform', '/',
   'platform,manufacturing,sbaio,procurement',
   'auto', 'auto', 'auto',
   'platform_admin',
   'lazydeepak@gmail.com',
   NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- NOTE: After running this SQL, visit the app in a browser.
-- The first boot will auto-discover apps, run pending migrations,
-- and sync menus/permissions from app manifests.
-- Login: lazydeepak@gmail.com / Password123!
