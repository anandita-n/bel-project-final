<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class ProjectDocumentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $projectId, int $userId, string $originalFilename, string $storedFilename, int $sizeBytes, ?string $mimeType = null): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO project_documents (project_id, user_id, original_filename, stored_filename, size_bytes, mime_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$projectId, $userId, $originalFilename, $storedFilename, $sizeBytes, $mimeType]);
        return (int)$this->db->lastInsertId();
    }

    public function forProject(int $projectId): array
    {
        $stmt = $this->db->prepare('
            SELECT d.*, u.name AS uploader_name
            FROM project_documents d
            JOIN users u ON u.id = d.user_id
            WHERE d.project_id = ?
            ORDER BY d.created_at DESC
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM project_documents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM project_documents WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function countForProject(int $projectId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) c FROM project_documents WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return (int)$stmt->fetch()['c'];
    }
}
