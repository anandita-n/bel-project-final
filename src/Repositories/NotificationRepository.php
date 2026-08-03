<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class NotificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** Never notifies a user about their own action. */
    public function create(int $userId, ?int $actorId, ?int $projectId, ?int $taskId, string $type, string $message, ?int $questionId = null, ?int $answerId = null): void
    {
        if ($actorId !== null && $actorId === $userId) {
            return;
        }
        $stmt = $this->db->prepare('
            INSERT INTO notifications (user_id, actor_id, project_id, task_id, question_id, answer_id, type, message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$userId, $actorId, $projectId, $taskId, $questionId, $answerId, $type, $message]);
    }

    /** Newest first, with project/task context joined. */
    public function forUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT n.*, p.project_code, p.name AS project_name, t.title AS task_title, a.name AS actor_name
            FROM notifications n
            LEFT JOIN projects p ON p.id = n.project_id
            LEFT JOIN tasks t ON t.id = n.task_id
            LEFT JOIN users a ON a.id = n.actor_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Forum answer-comment notifications only — powers the Notifications page's forum panel. */
    public function forumForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('
            SELECT n.*, fq.title AS question_title, a.name AS actor_name
            FROM notifications n
            LEFT JOIN forum_questions fq ON fq.id = n.question_id
            LEFT JOIN users a ON a.id = n.actor_id
            WHERE n.user_id = ? AND n.type = ?
            ORDER BY n.created_at DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$userId, 'forum_comment']);
        return $stmt->fetchAll();
    }

    public function unreadCountForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
    }
}
