USE bel_pms;
ALTER TABLE users
    ADD COLUMN job_title VARCHAR(150) NULL AFTER role,
    ADD COLUMN employment_type VARCHAR(50) NULL AFTER user_group,
    ADD COLUMN date_of_joining DATE NULL AFTER employment_type;
