<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class TaskCommentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $taskId, int $userId, string $comment): int
    {
        $stmt = $this->db->prepare('INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $userId, $comment]);
        return (int)$this->db->lastInsertId();
    }

    public function setMentions(int $commentId, array $userIds): void
    {
        if (!$userIds) {
            return;
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO task_comment_mentions (comment_id, user_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $stmt->execute([$commentId, $userId]);
        }
    }

    /** Oldest first (reads like a conversation), with author name/role and mentioned user ids joined. */
    public function forTask(int $taskId): array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, u.name AS author_name, u.role AS author_role, u.photo_filename AS author_photo_filename
            FROM task_comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.task_id = ?
            ORDER BY c.created_at ASC
        ');
        $stmt->execute([$taskId]);
        $comments = $stmt->fetchAll();

        if (!$comments) {
            return [];
        }

        $ids = array_map(fn($c) => (int)$c['id'], $comments);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $mentionStmt = $this->db->prepare("SELECT comment_id, user_id FROM task_comment_mentions WHERE comment_id IN ($placeholders)");
        $mentionStmt->execute($ids);
        $mentionsByComment = [];
        foreach ($mentionStmt->fetchAll() as $row) {
            $mentionsByComment[(int)$row['comment_id']][] = (int)$row['user_id'];
        }

        foreach ($comments as &$c) {
            $c['mention_ids'] = $mentionsByComment[(int)$c['id']] ?? [];
        }
        return $comments;
    }

    /** Grouped count per task, one query — [task_id => count]. */
    public function countsForTasks(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("SELECT task_id, COUNT(*) c FROM task_comments WHERE task_id IN ($placeholders) GROUP BY task_id");
        $stmt->execute($taskIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int)$row['task_id']] = (int)$row['c'];
        }
        return $counts;
    }
}
