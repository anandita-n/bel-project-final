ALTER TABLE projects ADD INDEX idx_projects_department (department);
ALTER TABLE projects ADD INDEX idx_projects_status (status);
ALTER TABLE users ADD INDEX idx_users_department (department);
