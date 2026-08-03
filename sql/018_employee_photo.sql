USE bel_pms;

ALTER TABLE users
    ADD COLUMN photo_filename VARCHAR(64) DEFAULT NULL AFTER user_group;
