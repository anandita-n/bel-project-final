-- Adds project archiving as an explicit, orthogonal lifecycle flag (not a status value) so
-- archiving never touches historical status/data — a project is "archived" iff archived_at
-- is not null. Nothing here cascades: archiving/restoring only ever updates these 3 columns
-- on the projects row itself.
ALTER TABLE projects
    ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER due_date,
    ADD COLUMN archived_by INT NULL DEFAULT NULL AFTER archived_at,
    ADD COLUMN archive_reason VARCHAR(255) NULL DEFAULT NULL AFTER archived_by,
    ADD CONSTRAINT fk_projects_archived_by FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD INDEX idx_projects_archived_at (archived_at);
