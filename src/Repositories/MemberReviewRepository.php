<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class MemberReviewRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $projectId, int $userId, int $authorId, string $comment): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO member_reviews (project_id, user_id, author_id, comment)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$projectId, $userId, $authorId, $comment]);
        return (int)$this->db->lastInsertId();
    }

    /** Newest first, with project and author names joined — used for the recipient's notifications list. */
    public function forUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, p.name AS project_name, p.project_code, a.name AS author_name, a.role AS author_role, a.photo_filename AS author_photo_filename
            FROM member_reviews r
            JOIN projects p ON p.id = r.project_id
            JOIN users a ON a.id = r.author_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Newest first — comments the given author left for this member on this project (drives the member drawer's history). */
    public function forProjectMemberByAuthor(int $projectId, int $userId, int $authorId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, comment, created_at
            FROM member_reviews
            WHERE project_id = ? AND user_id = ? AND author_id = ?
            ORDER BY created_at DESC
        ');
        $stmt->execute([$projectId, $userId, $authorId]);
        return $stmt->fetchAll();
    }

    /** Newest first — every comment left on this project (any member, any author), for the Overview tab's "Recent Comments" widget. */
    public function forProject(int $projectId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, u.name AS member_name, a.name AS author_name
            FROM member_reviews r
            JOIN users u ON u.id = r.user_id
            JOIN users a ON a.id = r.author_id
            WHERE r.project_id = ?
            ORDER BY r.created_at DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function unreadCountForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) c FROM member_reviews WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE member_reviews SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
    }
}
