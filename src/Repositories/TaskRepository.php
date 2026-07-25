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
            SELECT t.*, u.name AS assignee_name, u.role AS assignee_role
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.project_id = ?
            ORDER BY t.due_date IS NULL, t.due_date ASC, t.created_at DESC
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
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
            INSERT INTO tasks (project_id, title, description, assigned_to, priority, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_id'],
            $data['title'],
            $data['description'] ?: null,
            $data['assigned_to'] ?: null,
            $data['priority'],
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
