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

    public function findByEmployeeCode(string $employeeCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE employee_code = ? AND is_active = 1');
        $stmt->execute([$employeeCode]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setPhoto(int $id, ?string $filename): void
    {
        $stmt = $this->db->prepare('UPDATE users SET photo_filename = ? WHERE id = ?');
        $stmt->execute([$filename, $id]);
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
        $stmt = $this->db->query('SELECT id, name, role, employee_code, manager_id, email, telephone, department FROM users WHERE is_active = 1 ORDER BY name');
        return $stmt->fetchAll();
    }

    /** Filters a list of user ids down to only the ones still active — used to reject
     *  assignment attempts (tasks, project manager, etc) against deactivated employees. */
    public function activeIdsAmong(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT id FROM users WHERE is_active = 1 AND id IN ($placeholders)");
        $stmt->execute($ids);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /** Like activeIdsAmong(), but also excludes admins — admins don't do project/task work,
     *  so they're not valid task assignees or project team members. */
    public function assignableIdsAmong(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT id FROM users WHERE is_active = 1 AND role != 'admin' AND id IN ($placeholders)");
        $stmt->execute($ids);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    public function emailOrCodeExists(string $email, string $employeeCode): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE is_active = 1 AND (email = ? OR employee_code = ?)');
        $stmt->execute([$email, $employeeCode]);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (employee_code, name, email, password, must_change_password, role, department, manager_id, stream, telephone, user_group)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['employee_code'],
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            !empty($data['must_change_password']) ? 1 : 0,
            $data['role'],
            $data['department'] ?: null,
            $data['manager_id'] ?: null,
            $data['stream'] ?: null,
            $data['telephone'] ?: null,
            $data['user_group'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Next sequential BEL###### staff ID, based on the highest existing numeric suffix. */
    public function nextEmployeeCode(): string
    {
        $stmt = $this->db->query("SELECT employee_code FROM users WHERE employee_code REGEXP '^BEL[0-9]+$'");
        $max = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $num = (int)substr($code, 3);
            if ($num > $max) {
                $max = $num;
            }
        }
        return 'BEL' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Sets a new password hash; $mustChange marks it as a temporary/default password
     *  that the employee (or admin, via reset) should change at next login. */
    public function updatePassword(int $id, string $newPassword, bool $mustChange): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = ?, must_change_password = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $id]);
    }

    /** Counts of records elsewhere in the app that reference this user — used to decide whether a
     *  hard delete is safe (some FKs cascade-delete, which would destroy other people's data too). */
    public function linkedRecordCounts(int $id): array
    {
        $projectsManaged = $this->db->prepare('SELECT COUNT(*) c FROM projects WHERE manager_id = ?');
        $projectsManaged->execute([$id]);

        $tasksAssigned = $this->db->prepare('SELECT COUNT(DISTINCT task_id) c FROM task_assignees WHERE user_id = ?');
        $tasksAssigned->execute([$id]);

        $tasksCreated = $this->db->prepare('SELECT COUNT(*) c FROM tasks WHERE created_by = ?');
        $tasksCreated->execute([$id]);

        $discussionPosts = $this->db->prepare('
            SELECT
                (SELECT COUNT(*) FROM forum_questions WHERE user_id = ?) +
                (SELECT COUNT(*) FROM forum_answers WHERE user_id = ?) AS c
        ');
        $discussionPosts->execute([$id, $id]);

        $comments = $this->db->prepare('
            SELECT
                (SELECT COUNT(*) FROM task_comments WHERE user_id = ?) +
                (SELECT COUNT(*) FROM forum_question_comments WHERE user_id = ?) +
                (SELECT COUNT(*) FROM forum_answer_comments WHERE user_id = ?) AS c
        ');
        $comments->execute([$id, $id, $id]);

        return [
            'projects_managed' => (int)$projectsManaged->fetch()['c'],
            'tasks_assigned' => (int)$tasksAssigned->fetch()['c'],
            'tasks_created' => (int)$tasksCreated->fetch()['c'],
            'discussion_posts' => (int)$discussionPosts->fetch()['c'],
            'comments' => (int)$comments->fetch()['c'],
        ];
    }

    /** Only safe to call once linkedRecordCounts() confirms every count is zero — several FKs on
     *  this table cascade-delete, which would otherwise take other people's data down with it. */
    public function hardDelete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Frees the email/employee_code (both UNIQUE columns) by suffixing them with the row's own id,
     *  so a new employee can reuse the same email/staff-ID right after this one is removed. */
    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET is_active = 0,
                email = CONCAT(email, '.deleted', ?),
                employee_code = CONCAT(LEFT(employee_code, 8), '-DEL', ?)
            WHERE id = ?
        ");
        $stmt->execute([$id, $id, $id]);

        $stmt = $this->db->prepare('UPDATE users SET manager_id = NULL WHERE manager_id = ?');
        $stmt->execute([$id]);
    }

    public function updateManager(int $employeeId, ?int $managerId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET manager_id = ? WHERE id = ?');
        $stmt->execute([$managerId, $employeeId]);
    }

    /** Used by the "Edit Employee" modal: name, role, department, telephone, and who they report to. */
    public function updateProfile(int $id, string $name, string $role, ?string $department, ?int $managerId, ?string $telephone = null): void
    {
        $stmt = $this->db->prepare('UPDATE users SET name = ?, role = ?, department = ?, manager_id = ?, telephone = ? WHERE id = ?');
        $stmt->execute([$name, $role, $department ?: null, $managerId, $telephone ?: null, $id]);
    }

    /** Finds one active employee by exact employee code or name match (for the org chart search), with their manager and direct reports resolved. */
    public function searchOneWithHierarchy(string $query): ?array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE is_active = 1 AND (employee_code = ? OR name LIKE ?)
            ORDER BY (employee_code = ?) DESC, name ASC LIMIT 1
        ');
        $stmt->execute([$query, $like, $query]);
        $employee = $stmt->fetch();
        if (!$employee) {
            return null;
        }

        $manager = null;
        if ($employee['manager_id']) {
            $mstmt = $this->db->prepare('SELECT id, name, employee_code, role, department, manager_id, telephone, photo_filename FROM users WHERE id = ?');
            $mstmt->execute([$employee['manager_id']]);
            $manager = $mstmt->fetch() ?: null;
        }

        $rstmt = $this->db->prepare('SELECT id, name, employee_code, role, department, manager_id, telephone, photo_filename FROM users WHERE manager_id = ? AND is_active = 1 ORDER BY name');
        $rstmt->execute([$employee['id']]);
        $reports = $rstmt->fetchAll();

        return ['employee' => $employee, 'manager' => $manager, 'direct_reports' => $reports];
    }
}
