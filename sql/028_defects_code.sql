
ALTER TABLE defects
    ADD COLUMN code VARCHAR(20) NOT NULL DEFAULT '' AFTER project_id;

UPDATE defects SET code = CONCAT('DEF-', LPAD(id, 3, '0')) WHERE code = '';

ALTER TABLE defects
    ADD UNIQUE KEY uq_defects_code (code);
