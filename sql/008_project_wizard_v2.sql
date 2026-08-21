
ALTER TABLE projects
    ADD COLUMN IF NOT EXISTS department VARCHAR(100) DEFAULT NULL AFTER description,
    ADD COLUMN IF NOT EXISTS priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium' AFTER department,
    ADD COLUMN IF NOT EXISTS layout_type ENUM('kanban','list','timeline','blank','template') NOT NULL DEFAULT 'kanban' AFTER priority;

ALTER TABLE project_members
    ADD COLUMN IF NOT EXISTS permission_level ENUM('member','lead','manager') NOT NULL DEFAULT 'member' AFTER role_in_project;
