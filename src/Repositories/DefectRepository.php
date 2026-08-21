<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class DefectRepository
{
    public const SEVERITIES = ['critical' => 'Critical', 'major' => 'Major', 'minor' => 'Minor'];
    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listForProject(int $projectId): array
    {
        $stmt = $this->db->prepare('
            SELECT d.*, a.name AS assignee_name, a.role AS assignee_role, a.photo_filename AS assignee_photo_filename,
                   r.name AS reporter_name
            FROM defects d
            LEFT JOIN users a ON a.id = d.assigned_to
            LEFT JOIN users r ON r.id = d.reported_by
            WHERE d.project_id = ?
            ORDER BY d.created_at DESC
        ');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $defectId, int $projectId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM defects WHERE id = ? AND project_id = ?');
        $stmt->execute([$defectId, $projectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function codeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM defects WHERE code = ?');
        $stmt->execute([$code]);
        return (bool)$stmt->fetch();
    }

    /** Returns the freshly-joined row (with assignee/reporter names) so the API can hand it straight back. */
    public function findWithNames(int $defectId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT d.*, a.name AS assignee_name, a.role AS assignee_role, a.photo_filename AS assignee_photo_filename,
                   r.name AS reporter_name
            FROM defects d
            LEFT JOIN users a ON a.id = d.assigned_to
            LEFT JOIN users r ON r.id = d.reported_by
            WHERE d.id = ?
        ');
        $stmt->execute([$defectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** A starting suggestion for the Add Defect ID field — the field stays a normal editable text input. */
    public function nextSuggestedCode(): string
    {
        $count = (int)$this->db->query('SELECT COUNT(*) c FROM defects')->fetch()['c'];
        return 'DEF-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO defects (project_id, code, title, description, severity, status, assigned_to, reported_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_id'],
            $data['code'],
            $data['title'],
            ($data['description'] ?? '') ?: null,
            $data['severity'],
            $data['status'] ?? 'open',
            ($data['assigned_to'] ?? '') ?: null,
            $data['reported_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $defectId, int $projectId, array $data): void
    {
        $stmt = $this->db->prepare('
            UPDATE defects SET title = ?, description = ?, severity = ?, assigned_to = ?
            WHERE id = ? AND project_id = ?
        ');
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['severity'],
            $data['assigned_to'] ?: null,
            $defectId,
            $projectId,
        ]);
    }

    public function updateStatus(int $defectId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE defects SET status = ? WHERE id = ?');
        $stmt->execute([$status, $defectId]);
    }

    public function delete(int $defectId, int $projectId): void
    {
        $stmt = $this->db->prepare('DELETE FROM defects WHERE id = ? AND project_id = ?');
        $stmt->execute([$defectId, $projectId]);
    }
}
