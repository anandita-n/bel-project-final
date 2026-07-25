<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lightweight rows for the AJAX search picker: id, name, employee_code, role.
     *
     * $projectScope, if given, is ['project_id' => int, 'mode' => 'members'|'available']:
     *   'members'   — only the project's manager + current team members (for task assignment)
     *   'available' — active employees who are NOT already the manager or a team member (for adding new members)
     */
    public function search(string $query, int $limit = 8, ?array $rolesOnly = null, ?array $projectScope = null): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';

        if ($projectScope) {
            $projectId = $projectScope['project_id'];
            $onProject = '(
                EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = ? AND pm.user_id = u.id)
                OR EXISTS (SELECT 1 FROM projects p WHERE p.id = ? AND p.manager_id = u.id)
            )';
            $condition = $projectScope['mode'] === 'members' ? $onProject : 'NOT ' . $onProject;

            $sql = "SELECT u.id, u.name, u.employee_code, u.role FROM users u
                    WHERE u.is_active = 1 AND (u.name LIKE ? OR u.employee_code LIKE ?) AND $condition";
            $params = [$like, $like, $projectId, $projectId];
        } else {
            $sql = 'SELECT id, name, employee_code, role FROM users WHERE is_active = 1 AND (name LIKE ? OR employee_code LIKE ?)';
            $params = [$like, $like];
        }

        if ($rolesOnly) {
            $placeholders = implode(',', array_fill(0, count($rolesOnly), '?'));
            $sql .= " AND role IN ($placeholders)";
            $params = array_merge($params, $rolesOnly);
        }

        $sql .= ' ORDER BY name ASC LIMIT ' . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Full listing for employees.php, with manager name joined and optional text filter. */
    public function listActiveWithManager(string $query = ''): array
    {
        $sql = '
            SELECT e.*, m.name AS manager_name
            FROM users e
            LEFT JOIN users m ON m.id = e.manager_id
            WHERE e.is_active = 1
        ';
        $params = [];

        if ($query !== '') {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $sql .= ' AND (e.name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR e.department LIKE ?)';
            $params = [$like, $like, $like, $like];
        }

        $sql .= ' ORDER BY e.role = "admin" DESC, e.role = "manager" DESC, e.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listManagers(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, role, employee_code FROM users
            WHERE role IN ('admin','manager') AND is_active = 1
            ORDER BY name
        ");
        return $stmt->fetchAll();
    }

    public function listAllActive(): array
    {
        $stmt = $this->db->query('SELECT id, name, role, employee_code, manager_id FROM users WHERE is_active = 1 ORDER BY name');
        return $stmt->fetchAll();
    }

    public function emailOrCodeExists(string $email, string $employeeCode): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? OR employee_code = ?');
        $stmt->execute([$email, $employeeCode]);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (employee_code, name, email, password, role, department, manager_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['employee_code'],
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            $data['department'] ?: null,
            $data['manager_id'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);

        $stmt = $this->db->prepare('UPDATE users SET manager_id = NULL WHERE manager_id = ?');
        $stmt->execute([$id]);
    }

    public function updateManager(int $employeeId, ?int $managerId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET manager_id = ? WHERE id = ?');
        $stmt->execute([$managerId, $employeeId]);
    }

    /** Used by the "Edit Employee" modal: role, department, and who they report to. */
    public function updateProfile(int $id, string $role, ?string $department, ?int $managerId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET role = ?, department = ?, manager_id = ? WHERE id = ?');
        $stmt->execute([$role, $department ?: null, $managerId, $id]);
    }

    public function directReports(int $managerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE manager_id = ? AND is_active = 1 ORDER BY name');
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) c FROM users WHERE is_active = 1')->fetch()['c'];
    }
}
