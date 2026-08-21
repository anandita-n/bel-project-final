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

    public function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM assets WHERE asset_code = ?');
        $stmt->execute([$code]);
        return (bool)$stmt->fetch();
    }

    /** One past the highest existing "BEL-AST-###" code — same approach as
     *  ProjectRepository::nextSuggestedCode(), so it stays correct after deletions and ignores
     *  any differently-formatted codes someone typed in manually. */
    public function nextSuggestedCode(): string
    {
        $stmt = $this->db->query("SELECT asset_code FROM assets WHERE asset_code REGEXP '^BEL-AST-[0-9]+$'");
        $max = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $max = max($max, (int)substr($code, 8));
        }
        return 'BEL-AST-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
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

    /** Shared WHERE/params builder so search()/countSearch() stay in sync. */
    private function scope(string $q, string $category, string $status, string $department, ?int $employeeId): array
    {
        $where = [];
        $params = [];

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(a.asset_code LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR u.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($category !== '') {
            $where[] = 'a.category = ?';
            $params[] = $category;
        }
        if ($status !== '') {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        if ($department !== '') {
            if ($department === 'Unassigned') {
                $where[] = "(a.department IS NULL OR a.department = '')";
            } else {
                $where[] = 'a.department = ?';
                $params[] = $department;
            }
        }
        if ($employeeId !== null) {
            $where[] = 'a.assigned_to = ?';
            $params[] = $employeeId;
        }

        return [$where, $params];
    }

    /** Powers both the full admin/manager list (optionally scoped to one department, paginated)
     *  and an employee's "my assets" view ($employeeId scopes results to assets assigned to them). */
    public function search(string $q = '', string $category = '', string $status = '', ?int $employeeId = null, string $department = '', int $page = 1, int $perPage = 0): array
    {
        $sql = '
            SELECT a.id, a.asset_code, a.name, a.category, a.serial_number, a.assigned_to,
                a.department, a.purchase_date, a.warranty_expiry, a.status,
                u.name AS assignee_name, u.employee_code AS assignee_code
            FROM assets a LEFT JOIN users u ON u.id = a.assigned_to
        ';
        [$where, $params] = $this->scope($q, $category, $status, $department, $employeeId);
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.created_at DESC';

        if ($perPage > 0) {
            $perPage = min(max($perPage, 1), 200);
            $page = max($page, 1);
            $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countSearch(string $q = '', string $category = '', string $status = '', ?int $employeeId = null, string $department = ''): int
    {
        $sql = 'SELECT COUNT(*) c FROM assets a LEFT JOIN users u ON u.id = a.assigned_to';
        [$where, $params] = $this->scope($q, $category, $status, $department, $employeeId);
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    public function departmentSummary(): array
    {
        $sql = "
            SELECT COALESCE(NULLIF(department, ''), 'Unassigned') AS department, COUNT(*) AS asset_count
            FROM assets
            GROUP BY department
            ORDER BY department
        ";
        return $this->db->query($sql)->fetchAll();
    }
}
