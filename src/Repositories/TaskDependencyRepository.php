<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class TaskDependencyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** 'blocked_by' and 'depends_on' are directional (stored from $taskId's perspective).
     *  'related' is symmetric — a single row is queried from either side. */
    public function add(int $taskId, int $relatedTaskId, string $type): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO task_dependencies (task_id, related_task_id, type) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $relatedTaskId, $type]);
    }

    public function remove(int $taskId, int $relatedTaskId, string $type): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM task_dependencies
            WHERE type = ? AND ((task_id = ? AND related_task_id = ?) OR (task_id = ? AND related_task_id = ?))
        ');
        $stmt->execute([$type, $taskId, $relatedTaskId, $relatedTaskId, $taskId]);
    }

    /** Returns ['blocked_by' => [...], 'depends_on' => [...], 'related' => [...]], each entry {id, title, status}. */
    public function forTask(int $taskId): array
    {
        $result = ['blocked_by' => [], 'depends_on' => [], 'related' => []];

        foreach (['blocked_by', 'depends_on'] as $type) {
            $stmt = $this->db->prepare("
                SELECT t.id, t.title, t.status
                FROM task_dependencies d
                JOIN tasks t ON t.id = d.related_task_id
                WHERE d.task_id = ? AND d.type = ?
                ORDER BY t.title
            ");
            $stmt->execute([$taskId, $type]);
            $result[$type] = $stmt->fetchAll();
        }

        $stmt = $this->db->prepare("
            SELECT t.id, t.title, t.status
            FROM task_dependencies d
            JOIN tasks t ON t.id = (CASE WHEN d.task_id = ? THEN d.related_task_id ELSE d.task_id END)
            WHERE d.type = 'related' AND (d.task_id = ? OR d.related_task_id = ?)
            ORDER BY t.title
        ");
        $stmt->execute([$taskId, $taskId, $taskId]);
        $result['related'] = $stmt->fetchAll();

        return $result;
    }
}
