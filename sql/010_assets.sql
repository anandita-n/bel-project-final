
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category ENUM('laptop','desktop','monitor','keyboard','mouse','mobile_device','development_board','testing_equipment','network_equipment','software_license') NOT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,
    status ENUM('available','assigned','under_repair','retired','lost') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
