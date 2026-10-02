-- UniFlow Single Login migration
-- The Lost & Found module no longer needs its own admin account.
-- Keep the admins table; simply remove the old Lost & Found admin if it exists.
DELETE FROM admins WHERE role = 'lost_found';

-- IMPORTANT:
-- Do not run this statement if you intentionally want a separate
-- Lost & Found administrator account.
