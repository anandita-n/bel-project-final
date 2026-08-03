<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class AssetRepository
{
    public const CATEGORIES = [
        'laptop' => 'Laptop',
        'desktop' => 'Desktop',
        'monitor' => 'Monitor',
        'keyboard' => 'Keyboard',
        'mouse' => 'Mouse',
        'mobile_device' => 'Mobile Device',
        'development_board' => 'Development Board',
        'testing_equipment' => 'Testing Equipment',
        'network_equipment' => 'Network Equipment',
        'software_license' => 'Software License',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'under_repair' => 'Under Repair',
        'retired' => 'Retired',
        'lost' => 'Lost',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function nextSuggestedCode(): string
    {
        $count = (int)$this->db->query('SELECT COUNT(*) c FROM assets')->fetch()['c'];
        return 'BEL-AST-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM assets WHERE asset_code = ?');
        $stmt->execute([$code]);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO assets (asset_code, name, category, serial_number, department, purchase_date, warranty_expiry)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_code'],
            $data['name'],
            $data['category'],
            ($data['serial_number'] ?? '') ?: null,
            ($data['department'] ?? '') ?: null,
            ($data['purchase_date'] ?? '') ?: null,
            ($data['warranty_expiry'] ?? '') ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE assets SET name = ?, category = ?, serial_number = ?, department = ?, purchase_date = ?, warranty_expiry = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['name'],
            $data['category'],
            ($data['serial_number'] ?? '') ?: null,
            ($data['department'] ?? '') ?: null,
            ($data['purchase_date'] ?? '') ?: null,
            ($data['warranty_expiry'] ?? '') ?: null,
            $id,
        ]);
    }

    /** Sets who the asset is assigned to. Assigning sets status to 'assigned'; clearing sets it
     *  back to 'available' unless the asset is currently under_repair/retired/lost, which stays as-is. */
    public function assign(int $id, ?int $userId): void
    {
        if ($userId) {
            $stmt = $this->db->prepare('UPDATE assets SET assigned_to = ?, status = "assigned" WHERE id = ?');
            $stmt->execute([$userId, $id]);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE assets SET assigned_to = NULL, status = IF(status = 'assigned', 'available', status)
            WHERE id = ?
        ");
        $stmt->execute([$id]);
    }

    /** Status changes to retired/lost also clear the current assignment. */
    public function setStatus(int $id, string $status): void
    {
        if (in_array($status, ['retired', 'lost'], true)) {
            $stmt = $this->db->prepare('UPDATE assets SET status = ?, assigned_to = NULL WHERE id = ?');
        } else {
            $stmt = $this->db->prepare('UPDATE assets SET status = ? WHERE id = ?');
        }
        $stmt->execute([$status, $id]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, u.name AS assignee_name, u.employee_code AS assignee_code
            FROM assets a LEFT JOIN users u ON u.id = a.assigned_to
            WHERE a.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Powers both the full admin/manager list and an employee's "my assets" view
     *  ($employeeId scopes results to assets assigned to that user only). */
    public function search(string $q = '', string $category = '', string $status = '', ?int $employeeId = null): array
    {
        $sql = '
            SELECT a.*, u.name AS assignee_name, u.employee_code AS assignee_code
            FROM assets a LEFT JOIN users u ON u.id = a.assigned_to
            WHERE 1=1
        ';
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sql .= ' AND (a.asset_code LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR u.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($category !== '') {
            $sql .= ' AND a.category = ?';
            $params[] = $category;
        }
        if ($status !== '') {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }
        if ($employeeId !== null) {
            $sql .= ' AND a.assigned_to = ?';
            $params[] = $employeeId;
        }

        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
