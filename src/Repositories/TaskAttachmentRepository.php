<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class TaskAttachmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $taskId, int $userId, string $originalFilename, string $storedFilename, int $sizeBytes, ?string $mimeType): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO task_attachments (task_id, user_id, original_filename, stored_filename, size_bytes, mime_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$taskId, $userId, $originalFilename, $storedFilename, $sizeBytes, $mimeType]);
        return (int)$this->db->lastInsertId();
    }

    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, u.name AS uploader_name
            FROM task_attachments a
            JOIN users u ON u.id = a.user_id
            WHERE a.task_id = ?
            ORDER BY a.created_at DESC
        ');
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM task_attachments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM task_attachments WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Grouped count per task, one query — [task_id => count]. */
    public function countsForTasks(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("SELECT task_id, COUNT(*) c FROM task_attachments WHERE task_id IN ($placeholders) GROUP BY task_id");
        $stmt->execute($taskIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int)$row['task_id']] = (int)$row['c'];
        }
        return $counts;
    }
}
