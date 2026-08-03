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
            SELECT p.*, m.name AS manager_name, m.email AS manager_email, m.employee_code AS manager_employee_code, m.role AS manager_role, m.telephone AS manager_telephone, m.department AS manager_department, m.photo_filename AS manager_photo_filename
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

    /** Projects visible to this user: all of them for admin, else manager-of or member-of.
     *  $status, if given (active/on_hold/completed), narrows results to that status only. */
    public function listForUser(array $user, string $query = '', string $status = ''): array
    {
        $withCounts = '
            SELECT p.*, m.name AS manager_name, m.role AS manager_role, m.photo_filename AS manager_photo_filename,
            (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count
            FROM projects p JOIN users m ON m.id = p.manager_id
        ';

        if ($user['role'] === 'admin') {
            $sql = $withCounts;
            $params = [];
            $where = [];
            if ($query !== '') {
                $like = '%' . addcslashes($query, '%_\\') . '%';
                $where[] = '(p.name LIKE ? OR p.project_code LIKE ? OR m.name LIKE ?)';
                array_push($params, $like, $like, $like);
            }
            if ($status !== '') {
                $where[] = 'p.status = ?';
                $params[] = $status;
            }
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY p.created_at DESC';
        } else {
            $sql = '
                SELECT DISTINCT p.*, m.name AS manager_name, m.role AS manager_role, m.photo_filename AS manager_photo_filename,
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
                array_push($params, $like, $like, $like);
            }
            if ($status !== '') {
                $sql .= ' AND p.status = ?';
                $params[] = $status;
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

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /** Cascades in the DB to project_members, tasks (and everything hanging off tasks),
     *  member_reviews, and notifications. Callers are responsible for deleting any files
     *  on disk (project documents, task attachments) before calling this. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }

/** A starting suggestion for the Step 1 code field — the field stays a normal editable text input. */
    public function nextSuggestedCode(): string
    {
        $count = (int)$this->db->query('SELECT COUNT(*) c FROM projects')->fetch()['c'];
        return 'BEL-PRJ-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO projects (project_code, name, description, department, priority, manager_id, start_date, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_code'],
            $data['name'],
            $data['description'] ?: null,
            $data['department'] ?: null,
            in_array($data['priority'] ?? '', ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium',
            $data['manager_id'],
            $data['start_date'] ?: null,
            $data['due_date'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function members(int $projectId): array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.name, u.email, u.role AS system_role, u.employee_code, u.department, u.photo_filename,
                   pm.role_in_project, pm.permission_level, pm.assigned_at
            FROM project_members pm JOIN users u ON u.id = pm.user_id
            WHERE pm.project_id = ? ORDER BY u.name
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $projectId, int $userId, string $roleInProject, string $permissionLevel = 'member'): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO project_members (project_id, user_id, role_in_project, permission_level) VALUES (?, ?, ?, ?)');
        $stmt->execute([$projectId, $userId, $roleInProject, $permissionLevel]);
    }

    public function updateMember(int $projectId, int $userId, string $roleInProject, string $permissionLevel): void
    {
        $stmt = $this->db->prepare('UPDATE project_members SET role_in_project = ?, permission_level = ? WHERE project_id = ? AND user_id = ?');
        $stmt->execute([$roleInProject, $permissionLevel, $projectId, $userId]);
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
}
