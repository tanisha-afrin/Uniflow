-- Facilities / Maintenance belongs under Administrative, not as a portal.
ALTER TABLE reports
    MODIFY category ENUM('technical','administrative','facilities','proctorial','lost_found') NOT NULL;

ALTER TABLE admins
    MODIFY role ENUM('technical','administrative','facilities','proctorial','lost_found') NOT NULL;

ALTER TABLE admin_access
    MODIFY access_area ENUM('technical','administrative','facilities','proctorial','lost_found') NOT NULL;

START TRANSACTION;

UPDATE reports
SET category = 'administrative'
WHERE category = 'facilities';

UPDATE admins
SET role = 'administrative'
WHERE role = 'facilities';

DELETE facilities_access
FROM admin_access AS facilities_access
INNER JOIN admin_access AS administrative_access
    ON administrative_access.admin_id = facilities_access.admin_id
    AND administrative_access.access_area = 'administrative'
WHERE facilities_access.access_area = 'facilities';

UPDATE admin_access
SET access_area = 'administrative'
WHERE access_area = 'facilities';

COMMIT;

ALTER TABLE reports
    MODIFY category ENUM('technical','administrative','proctorial','lost_found') NOT NULL;

ALTER TABLE admins
    MODIFY role ENUM('technical','administrative','proctorial','lost_found') NOT NULL;

ALTER TABLE admin_access
    MODIFY access_area ENUM('technical','administrative','proctorial','lost_found') NOT NULL;