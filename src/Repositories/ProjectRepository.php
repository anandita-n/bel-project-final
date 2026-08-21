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
            SELECT p.*, m.name AS manager_name, m.email AS manager_email, m.employee_code AS manager_employee_code, m.role AS manager_role, m.telephone AS manager_telephone, m.department AS manager_department, m.photo_filename AS manager_photo_filename,
                ab.name AS archived_by_name
            FROM projects p JOIN users m ON m.id = p.manager_id
            LEFT JOIN users ab ON ab.id = p.archived_by
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

    /** Shared WHERE/params builder for listForUser()/countForUser() so the two stay in sync. */
    /** $archived: false (default) = active lists, only non-archived projects. true = only
     *  archived projects (for the Archived Projects view — same visibility rules as active). */
    private function scopeForUser(array $user, string $query, string $status, string $department, bool $archived = false): array
    {
        $where = [];
        $params = [];

        $where[] = $archived ? 'p.archived_at IS NOT NULL' : 'p.archived_at IS NULL';

        if ($user['role'] !== 'admin') {
            // Non-admins see projects they manage or are a member of, plus every other project in
            // their own department (read-only — project_detail.php restricts what those show).
            $where[] = '(p.manager_id = ? OR pm2.user_id = ? OR (p.department <> \'\' AND p.department = ?))';
            array_push($params, $user['id'], $user['id'], (string)($user['department'] ?? ''));
        }
        if ($query !== '') {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.project_code LIKE ? OR m.name LIKE ? OR p.department LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($department !== '') {
            if ($department === 'Unassigned') {
                $where[] = "(p.department IS NULL OR p.department = '')";
            } else {
                $where[] = 'p.department = ?';
                $params[] = $department;
            }
        }
        return [$where, $params];
    }

    /** Projects visible to this user: all of them for admin, else manager-of or member-of.
     *  $status, if given (active/on_hold/completed), narrows results to that status only.
     *  $department, if given, narrows to that exact department ("Unassigned" matches NULL/empty).
     *  $page/$perPage: pass $perPage <= 0 for the old "no pagination" behavior (small result sets only).
     *  $archived: false = active projects only (default, matches every existing list view);
     *  true = archived projects only (the Archived Projects view). */
    public function listForUser(array $user, string $query = '', string $status = '', string $department = '', int $page = 1, int $perPage = 0, bool $archived = false): array
    {
        $distinct = $user['role'] !== 'admin' ? 'DISTINCT ' : '';
        $joinMembers = $user['role'] !== 'admin' ? 'LEFT JOIN project_members pm2 ON pm2.project_id = p.id' : '';

        $sql = "
            SELECT {$distinct}p.id, p.project_code, p.name, p.department, p.status, p.manager_id,
                m.name AS manager_name, m.role AS manager_role, m.photo_filename AS manager_photo_filename,
                p.archived_at, p.archived_by, p.archive_reason, p.due_date,
                ab.name AS archived_by_name,
                (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count
            FROM projects p
            JOIN users m ON m.id = p.manager_id
            LEFT JOIN users ab ON ab.id = p.archived_by
            {$joinMembers}
        ";

        [$where, $params] = $this->scopeForUser($user, $query, $status, $department, $archived);
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= $archived ? ' ORDER BY p.archived_at DESC' : ' ORDER BY p.created_at DESC';

        if ($perPage > 0) {
            $perPage = min(max($perPage, 1), 200);
            $page = max($page, 1);
            $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Total count matching the same filters as listForUser() — for pagination. */
    public function countForUser(array $user, string $query = '', string $status = '', string $department = '', bool $archived = false): int
    {
        $joinMembers = $user['role'] !== 'admin' ? 'LEFT JOIN project_members pm2 ON pm2.project_id = p.id' : '';
        $countExpr = $user['role'] !== 'admin' ? 'COUNT(DISTINCT p.id)' : 'COUNT(*)';

        $sql = "
            SELECT {$countExpr} c
            FROM projects p
            JOIN users m ON m.id = p.manager_id
            {$joinMembers}
        ";
        [$where, $params] = $this->scopeForUser($user, $query, $status, $department, $archived);
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    /** Department name + project count, for the department-index view (admins see every
     *  department; everyone else only departments among their own manager-of/member-of projects).
     *  Archived projects are excluded — they're reached through Archived Projects instead. */
    public function departmentSummaryForUser(array $user): array
    {
        if ($user['role'] === 'admin') {
            $sql = "
                SELECT COALESCE(NULLIF(department, ''), 'Unassigned') AS department, COUNT(*) AS project_count
                FROM projects
                WHERE archived_at IS NULL
                GROUP BY department
                ORDER BY department
            ";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        }

        $sql = "
            SELECT COALESCE(NULLIF(p.department, ''), 'Unassigned') AS department, COUNT(DISTINCT p.id) AS project_count
            FROM projects p
            LEFT JOIN project_members pm2 ON pm2.project_id = p.id
            WHERE p.archived_at IS NULL AND (p.manager_id = ? OR pm2.user_id = ? OR (p.department <> '' AND p.department = ?))
            GROUP BY department
            ORDER BY department
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user['id'], $user['id'], (string)($user['department'] ?? '')]);
        return $stmt->fetchAll();
    }

    public function recentForUser(array $user, int $limit = 8): array
    {
        $rows = $this->listForUser($user);
        return array_slice($rows, 0, $limit);
    }

    /** Any non-admin can also view (read-only) every other project in their own department —
     *  project_detail.php and hasFullAccess() below use isMember()/manager_id to tell that
     *  broader read-only access apart from full membership. */
    public function userHasAccess(int $projectId, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        $stmt = $this->db->prepare('
            SELECT 1 FROM projects p
            LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
            WHERE p.id = ? AND (p.manager_id = ? OR pm.user_id IS NOT NULL OR (p.department <> \'\' AND p.department = ?))
        ');
        $stmt->execute([$user['id'], $projectId, $user['id'], (string)($user['department'] ?? '')]);
        return (bool)$stmt->fetch();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /** Edits the project's core info card — name, description, department, due date.
     *  Manager/status/priority/layout are changed elsewhere and stay untouched here. */
    public function updateDetails(int $id, string $name, string $description, string $department, ?string $dueDate): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET name = ?, description = ?, department = ?, due_date = ? WHERE id = ?');
        $stmt->execute([$name, $description, $department, $dueDate, $id]);
    }

    public function updateManager(int $id, int $managerId): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET manager_id = ? WHERE id = ?');
        $stmt->execute([$managerId, $id]);
    }

    /** Used to block deactivating a user while they still manage projects — surfaces which
     *  projects need a new manager assigned first. Archived projects don't count: nothing
     *  active happens on them, so they don't need a reachable manager. */
    public function managedBy(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, name, project_code FROM projects WHERE manager_id = ? AND archived_at IS NULL ORDER BY name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Archiving is a soft, reversible flag — it never touches tasks/defects/members/documents/
     *  notifications, it just sets these 3 columns on the project row itself. */
    public function archive(int $id, int $archivedBy, ?string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET archived_at = NOW(), archived_by = ?, archive_reason = ? WHERE id = ?');
        $stmt->execute([$archivedBy, $reason ?: null, $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE projects SET archived_at = NULL, archived_by = NULL, archive_reason = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Counts of tasks/defects/documents/non-manager members — used to decide whether a project
     *  is "meaningfully empty" enough to allow permanent deletion instead of requiring archive. */
    public function activityCounts(int $id): array
    {
        $tasks = $this->db->prepare('SELECT COUNT(*) c FROM tasks WHERE project_id = ?');
        $tasks->execute([$id]);
        $defects = $this->db->prepare('SELECT COUNT(*) c FROM defects WHERE project_id = ?');
        $defects->execute([$id]);
        $documents = $this->db->prepare('SELECT COUNT(*) c FROM project_documents WHERE project_id = ?');
        $documents->execute([$id]);
        $members = $this->db->prepare('SELECT COUNT(*) c FROM project_members WHERE project_id = ?');
        $members->execute([$id]);
        return [
            'tasks' => (int)$tasks->fetch()['c'],
            'defects' => (int)$defects->fetch()['c'],
            'documents' => (int)$documents->fetch()['c'],
            'members' => (int)$members->fetch()['c'],
        ];
    }

    /** Cascades in the DB to project_members, tasks (and everything hanging off tasks),
     *  member_reviews, and notifications. Only meant for a project with no meaningful activity
     *  (see activityCounts()) — anything with real history should be archived, not deleted.
     *  Callers are responsible for deleting any files on disk (project documents, task
     *  attachments) before calling this. */
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }

/** A starting suggestion for the Step 1 code field — the field stays a normal editable text input. */
    /** One past the highest existing "PRJ###" code — matches the seed data's format
     *  (PRJ001, PRJ002, ...) rather than a row count, so it stays correct after deletions and
     *  ignores any differently-formatted codes someone typed in manually. */
    public function nextSuggestedCode(): string
    {
        $stmt = $this->db->query("SELECT project_code FROM projects WHERE project_code REGEXP '^PRJ[0-9]+$'");
        $max = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $code) {
            $max = max($max, (int)substr($code, 3));
        }
        return 'PRJ' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
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

    /** True if the user has full workspace access to this project (admin, its manager, or an
     *  actual project_members row) — narrower than userHasAccess(), which also lets any
     *  non-admin view (read-only) other projects in their own department. Task/document/
     *  comment/attachment/dependency reads must gate on this instead, so a department-only
     *  viewer can't reach that data straight through the API even though the page loads. */
    public function hasFullAccess(array $project, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        if ((int)$user['id'] === (int)$project['manager_id']) {
            return true;
        }
        return $this->isMember((int)$project['id'], (int)$user['id']);
    }

    /** All projects (manager-of or member-of), with the employee's role on each — used on
     *  employee_detail.php's Projects tab and the "My Projects" sidebar view. Also joins the
     *  manager's name/role/photo and member count so render_project_row() can render it directly. */
    public function projectsForEmployee(int $userId): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*,
                CASE WHEN p.manager_id = :id THEN "Project Manager" ELSE pm.role_in_project END AS role_in_project,
                m.name AS manager_name, m.role AS manager_role, m.photo_filename AS manager_photo_filename,
                (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.id) AS member_count
            FROM projects p
            JOIN users m ON m.id = p.manager_id
            LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = :id
            WHERE p.manager_id = :id OR pm.user_id = :id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }
}
