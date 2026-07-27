<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class TaskRepository
{
    public const STATUSES = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listForProject(int $projectId): array
    {
        $stmt = $this->db->prepare('
            SELECT t.*, u.name AS assignee_name, u.role AS assignee_role, c.name AS creator_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            LEFT JOIN users c ON c.id = t.created_by
            WHERE t.project_id = ?
            ORDER BY t.due_date IS NULL, t.due_date ASC, t.created_at DESC
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** Open (not-done) tasks assigned to this user across every project — powers the dashboard's "My Tasks" widget. */
    public function listOpenForAssignee(int $userId, int $limit = 8): array
    {
        $stmt = $this->db->prepare('
            SELECT t.*, p.name AS project_name, p.project_code
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            WHERE t.assigned_to = ? AND t.status != "done"
            ORDER BY t.due_date IS NULL, t.due_date ASC, t.priority = "high" DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function countOpenForAssignee(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) c FROM tasks WHERE assigned_to = ? AND status != "done"');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    }

    public function find(int $taskId, int $projectId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND project_id = ?');
        $stmt->execute([$taskId, $projectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO tasks (project_id, title, description, assigned_to, priority, start_date, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_id'],
            $data['title'],
            $data['description'] ?: null,
            $data['assigned_to'] ?: null,
            $data['priority'],
            $data['start_date'] ?: null,
            $data['due_date'] ?: null,
            $data['created_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Returns the freshly-joined row (with assignee name/role) so the API can hand it straight back. */
    public function findWithAssignee(int $taskId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT t.*, u.name AS assignee_name, u.role AS assignee_role
            FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.id = ?
        ');
        $stmt->execute([$taskId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStatus(int $taskId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE tasks SET status = ? WHERE id = ?');
        $stmt->execute([$status, $taskId]);
    }

    public function update(int $taskId, int $projectId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, start_date = ?, due_date = ?
            WHERE id = ? AND project_id = ?
        ');
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['assigned_to'] ?: null,
            $data['priority'],
            $data['start_date'] ?: null,
            $data['due_date'] ?: null,
            $taskId,
            $projectId,
        ]);
    }

    public function delete(int $taskId, int $projectId): void
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ? AND project_id = ?');
        $stmt->execute([$taskId, $projectId]);
    }

    public function groupByStatus(array $tasks): array
    {
        $grouped = array_fill_keys(array_keys(self::STATUSES), []);
        foreach ($tasks as $t) {
            $grouped[$t['status']][] = $t;
        }
        return $grouped;
    }
}
