USE bel_pms;

CREATE TABLE IF NOT EXISTS task_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO task_labels (name, color) VALUES
    ('Backend', 'navy'),
    ('Frontend', 'blue'),
    ('Testing', 'green'),
    ('Documentation', 'gray'),
    ('Hardware', 'red'),
    ('Firmware', 'gold'),
    ('Research', 'teal'),
    ('Integration', 'blue');

CREATE TABLE IF NOT EXISTS task_label_links (
    task_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (task_id, label_id),
    CONSTRAINT fk_tll_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tll_label FOREIGN KEY (label_id) REFERENCES task_labels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS task_subtasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subtask_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB;
