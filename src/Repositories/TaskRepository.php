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

    /** Open (not-done) tasks assigned to this user across every project — powers the dashboard's "My Tasks" widget.
     *  Joins task_assignees rather than the legacy assigned_to column so a task shows up for every assignee. */
    public function listOpenForAssignee(int $userId, int $limit = 8): array
    {
        $stmt = $this->db->prepare('
            SELECT t.*, p.name AS project_name, p.project_code
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            JOIN task_assignees ta ON ta.task_id = t.id
            WHERE ta.user_id = ? AND t.status != "done"
            ORDER BY t.due_date IS NULL, t.due_date ASC, t.priority = "high" DESC
            LIMIT ' . (int)$limit . '
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Batch — [task_id => [{id,name,role}, ...]] ordered by assigned_at so the primary (first) assignee stays first. */
    public function assigneesForTasks(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("
            SELECT ta.task_id, u.id, u.name, u.role, u.photo_filename
            FROM task_assignees ta
            JOIN users u ON u.id = ta.user_id
            WHERE ta.task_id IN ($placeholders)
            ORDER BY ta.assigned_at ASC, ta.user_id ASC
        ");
        $stmt->execute($taskIds);
        $byTask = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTask[(int)$row['task_id']][] = ['id' => (int)$row['id'], 'name' => $row['name'], 'role' => $row['role'], 'has_photo' => !empty($row['photo_filename'])];
        }
        return $byTask;
    }

    /** Replaces a task's full assignee list. Also mirrors the first id into the legacy assigned_to
     *  column so existing single-assignee consumers (notifications, filters) keep working unchanged. */
    public function setTaskAssignees(int $taskId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $del = $this->db->prepare('DELETE FROM task_assignees WHERE task_id = ?');
        $del->execute([$taskId]);

        if ($userIds) {
            $stmt = $this->db->prepare('INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)');
            foreach ($userIds as $userId) {
                $stmt->execute([$taskId, $userId]);
            }
        }

        $primary = $userIds[0] ?? null;
        $upd = $this->db->prepare('UPDATE tasks SET assigned_to = ? WHERE id = ?');
        $upd->execute([$primary, $taskId]);
    }

    public function isAssignee(int $taskId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM task_assignees WHERE task_id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        return (bool)$stmt->fetch();
    }

    public function find(int $taskId, int $projectId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? AND project_id = ?');
        $stmt->execute([$taskId, $projectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Optional $data['assignees'] (array of user ids) sets the full multi-assignee list after insert;
     *  otherwise falls back to the single legacy assigned_to field (used by the quick-add flow). */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO tasks (project_id, title, description, assigned_to, priority, start_date, due_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['project_id'],
            $data['title'],
            ($data['description'] ?? '') ?: null,
            ($data['assigned_to'] ?? '') ?: null,
            $data['priority'],
            ($data['start_date'] ?? '') ?: null,
            ($data['due_date'] ?? '') ?: null,
            $data['created_by'],
        ]);
        $taskId = (int)$this->db->lastInsertId();

        if (isset($data['assignees'])) {
            $this->setTaskAssignees($taskId, $data['assignees']);
        } elseif (!empty($data['assigned_to'])) {
            $this->setTaskAssignees($taskId, [(int)$data['assigned_to']]);
        }

        return $taskId;
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

    /** Deletes every given task that belongs to this project — powers the List/Grid bulk-delete action. */
    public function bulkDelete(array $taskIds, int $projectId): void
    {
        if (!$taskIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE project_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$projectId], $taskIds));
    }

    /** All rows from the fixed label lookup table — used for the picker/filter chips. */
    public function allLabels(): array
    {
        $stmt = $this->db->query('SELECT id, name, color FROM task_labels ORDER BY name');
        return $stmt->fetchAll();
    }

    /** Newest-first per task, grouped in one query — [task_id => [{id,name,color}, ...]]. */
    public function labelsForTasks(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("
            SELECT tll.task_id, tl.id, tl.name, tl.color
            FROM task_label_links tll
            JOIN task_labels tl ON tl.id = tll.label_id
            WHERE tll.task_id IN ($placeholders)
            ORDER BY tl.name
        ");
        $stmt->execute($taskIds);
        $byTask = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTask[(int)$row['task_id']][] = ['id' => (int)$row['id'], 'name' => $row['name'], 'color' => $row['color']];
        }
        return $byTask;
    }

    public function setTaskLabels(int $taskId, array $labelIds): void
    {
        $del = $this->db->prepare('DELETE FROM task_label_links WHERE task_id = ?');
        $del->execute([$taskId]);
        if (!$labelIds) {
            return;
        }
        $stmt = $this->db->prepare('INSERT IGNORE INTO task_label_links (task_id, label_id) VALUES (?, ?)');
        foreach (array_unique(array_map('intval', $labelIds)) as $labelId) {
            $stmt->execute([$taskId, $labelId]);
        }
    }

    /** Grouped in one query — [task_id => [{id,title,is_done}, ...]] ordered by position then id. */
    public function subtasksForTasks(array $taskIds): array
    {
        if (!$taskIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id, task_id, title, is_done
            FROM task_subtasks
            WHERE task_id IN ($placeholders)
            ORDER BY position ASC, id ASC
        ");
        $stmt->execute($taskIds);
        $byTask = [];
        foreach ($stmt->fetchAll() as $row) {
            $byTask[(int)$row['task_id']][] = ['id' => (int)$row['id'], 'title' => $row['title'], 'is_done' => (bool)$row['is_done']];
        }
        return $byTask;
    }

    public function addSubtask(int $taskId, string $title): array
    {
        $posStmt = $this->db->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM task_subtasks WHERE task_id = ?');
        $posStmt->execute([$taskId]);
        $position = (int)$posStmt->fetch()['next_pos'];

        $stmt = $this->db->prepare('INSERT INTO task_subtasks (task_id, title, position) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $title, $position]);
        $id = (int)$this->db->lastInsertId();

        return ['id' => $id, 'title' => $title, 'is_done' => false];
    }

    public function toggleSubtask(int $subtaskId, int $taskId, bool $isDone): void
    {
        $stmt = $this->db->prepare('UPDATE task_subtasks SET is_done = ? WHERE id = ? AND task_id = ?');
        $stmt->execute([$isDone ? 1 : 0, $subtaskId, $taskId]);
    }

    public function deleteSubtask(int $subtaskId, int $taskId): void
    {
        $stmt = $this->db->prepare('DELETE FROM task_subtasks WHERE id = ? AND task_id = ?');
        $stmt->execute([$subtaskId, $taskId]);
    }

    /** Bulk-update a whitelisted set of fields across many tasks at once — powers the List view's bulk action bar. */
    public function bulkUpdate(array $taskIds, int $projectId, array $fields): void
    {
        if (!$taskIds) {
            return;
        }
        $allowed = ['status', 'priority', 'assigned_to'];
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        foreach ($fields as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                continue;
            }
            $stmt = $this->db->prepare("UPDATE tasks SET $field = ? WHERE project_id = ? AND id IN ($placeholders)");
            $stmt->execute(array_merge([$value === '' ? null : $value, $projectId], $taskIds));
        }
    }
}
