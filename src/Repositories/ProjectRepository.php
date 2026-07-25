<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class ProjectRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, m.name AS manager_name, m.email AS manager_email, m.employee_code AS manager_employee_code
            FROM projects p JOIN users m ON m.id = p.manager_id
            WHERE p.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM projects WHERE project_code = ?');
        $stmt->execute([$code]);
        return (bool)$stmt->fetch();
    }

    /** Projects visible to this user: all of them for admin, else manager-of or member-of. */
    public function listForUser(array $user, string $query = ''): array
    {
        $withCounts = '
            SELECT p.*, m.name AS manager_name,
            (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count
            FROM projects p JOIN users m ON m.id = p.manager_id
        ';

        if ($user['role'] === 'admin') {
            $sql = $withCounts;
            $params = [];
            if ($query !== '') {
                $like = '%' . addcslashes($query, '%_\\') . '%';
                $sql .= ' WHERE p.name LIKE ? OR p.project_code LIKE ? OR m.name LIKE ?';
                $params = [$like, $like, $like];
            }
            $sql .= ' ORDER BY p.created_at DESC';
        } else {
            $sql = '
                SELECT DISTINCT p.*, m.name AS manager_name,
                (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count
                FROM projects p
                JOIN users m ON m.id = p.manager_id
                LEFT JOIN project_members pm2 ON pm2.project_id = p.id
                WHERE (p.manager_id = ? OR pm2.user_id = ?)
            ';
            $params = [$user['id'], $user['id']];
            if ($query !== '') {
                $like = '%' . addcslashes($query, '%_\\') . '%';
                $sql .= ' AND (p.name LIKE ? OR p.project_code LIKE ? OR m.name LIKE ?)';
                $params = array_merge($params, [$like, $like, $like]);
            }
            $sql .= ' ORDER BY p.created_at DESC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function recentForUser(array $user, int $limit = 8): array
    {
        $rows = $this->listForUser($user);
        return array_slice($rows, 0, $limit);
    }

    public function userHasAccess(int $projectId, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        $stmt = $this->db->prepare('
            SELECT 1 FROM projects p
            LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
            WHERE p.id = ? AND (p.manager_id = ? OR pm.user_id IS NOT NULL)
        ');
        $stmt->execute([$user['id'], $projectId, $user['id']]);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO projects (project_code, name, description, manager_id, start_date)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_code'],
            $data['name'],
            $data['description'] ?: null,
            $data['manager_id'],
            $data['start_date'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function members(int $projectId): array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.name, u.email, u.role AS system_role, u.employee_code, u.department, pm.role_in_project
            FROM project_members pm JOIN users u ON u.id = pm.user_id
            WHERE pm.project_id = ? ORDER BY u.name
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $projectId, int $userId, string $roleInProject): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO project_members (project_id, user_id, role_in_project) VALUES (?, ?, ?)');
        $stmt->execute([$projectId, $userId, $roleInProject]);
    }

    public function removeMember(int $projectId, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?');
        $stmt->execute([$projectId, $userId]);
    }

    public function isMember(int $projectId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?');
        $stmt->execute([$projectId, $userId]);
        return (bool)$stmt->fetch();
    }

    /** All projects (manager-of or member-of), with the employee's role on each — used on employee_detail.php. */
    public function projectsForEmployee(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*,
                CASE WHEN p.manager_id = :id THEN "Project Manager" ELSE pm.role_in_project END AS role_in_project
            FROM projects p
            LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = :id
            WHERE p.manager_id = :id OR pm.user_id = :id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) c FROM projects')->fetch()['c'];
    }

    public function countActive(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) c FROM projects WHERE status = 'active'")->fetch()['c'];
    }
}
