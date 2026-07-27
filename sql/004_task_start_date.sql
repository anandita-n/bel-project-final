USE bel_pms;

ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL AFTER description;
