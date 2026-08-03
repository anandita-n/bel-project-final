<?php

namespace App\Repositories;

use App\Database;
use PDO;

final class ForumRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function createQuestion(int $userId, string $title, string $body, array $tagNames): int
    {
        $stmt = $this->db->prepare('INSERT INTO forum_questions (user_id, title, body) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $title, $body]);
        $questionId = (int)$this->db->lastInsertId();

        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }
            $tagId = $this->findOrCreateTag($tagName);
            $link = $this->db->prepare('INSERT IGNORE INTO forum_question_tags (question_id, tag_id) VALUES (?, ?)');
            $link->execute([$questionId, $tagId]);
        }

        return $questionId;
    }

    private function findOrCreateTag(string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM forum_tags WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }
        $insert = $this->db->prepare('INSERT INTO forum_tags (name) VALUES (?)');
        $insert->execute([$name]);
        return (int)$this->db->lastInsertId();
    }

    /** Posting a new answer counts as activity on the question, for the "Recently Updated" sort. */
    public function createAnswer(int $questionId, int $userId, string $body): int
    {
        $stmt = $this->db->prepare('INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$questionId, $userId, $body]);
        // lastInsertId() must be read before any other statement runs on this connection —
        // an UPDATE (like touchQuestion()) resets it back to 0 on this PDO driver.
        $answerId = (int)$this->db->lastInsertId();
        $this->touchQuestion($questionId);
        return $answerId;
    }

    private function touchQuestion(int $questionId): void
    {
        $stmt = $this->db->prepare('UPDATE forum_questions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$questionId]);
    }

    private static function deriveStatus(?int $acceptedAnswerId): string
    {
        return $acceptedAnswerId ? 'solved' : 'open';
    }

    /** Shared WHERE/JOIN builder for listQuestions()/countQuestions() so the two stay in sync. */
    private function questionsQueryParts(string $q, ?int $tagId, string $status, string $department): array
    {
        $joinSql = '';
        $params = [];
        $where = [];

        if ($tagId) {
            $joinSql = ' JOIN forum_question_tags qt ON qt.question_id = q.id AND qt.tag_id = ?';
            $params[] = $tagId;
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(q.title LIKE ? OR q.body LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($status === 'open') {
            $where[] = 'q.accepted_answer_id IS NULL';
        } elseif ($status === 'solved') {
            $where[] = 'q.accepted_answer_id IS NOT NULL';
        }
        if ($department !== '') {
            $where[] = 'u.department = ?';
            $params[] = $department;
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        return [$joinSql, $whereSql, $params];
    }

    /** $sort: 'recent' (default) | 'most_helpful' | 'updated'.
     *  $status: '' (all) | 'open' | 'solved'. $department filters on the asking user's department.
     *  $page is 1-indexed; results are limited to $perPage rows — pair with countQuestions() for the total. */
    public function listQuestions(string $q = '', ?int $tagId = null, string $sort = 'recent', string $status = '', string $department = '', int $page = 1, int $perPage = 15): array
    {
        [$joinSql, $whereSql, $params] = $this->questionsQueryParts($q, $tagId, $status, $department);

        $sql = '
            SELECT q.*, u.name AS author_name, u.role AS author_role, u.department AS author_department, u.photo_filename AS author_photo_filename,
                (SELECT COUNT(*) FROM forum_answers a WHERE a.question_id = q.id) AS answer_count,
                COALESCE((SELECT SUM(1) FROM forum_answer_helpful h JOIN forum_answers a2 ON a2.id = h.answer_id WHERE a2.question_id = q.id), 0) AS helpful_count
            FROM forum_questions q
            JOIN users u ON u.id = q.user_id
        ' . $joinSql . $whereSql;

        $sql .= match ($sort) {
            'most_helpful' => ' ORDER BY helpful_count DESC',
            'updated' => ' ORDER BY q.updated_at DESC',
            default => ' ORDER BY q.created_at DESC',
        };

        $perPage = max(1, $perPage);
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $tagsByQuestion = $this->tagsForQuestions(array_map(fn($r) => (int)$r['id'], $rows));
        foreach ($rows as &$row) {
            $row['tags'] = $tagsByQuestion[(int)$row['id']] ?? [];
            $row['status'] = self::deriveStatus($row['accepted_answer_id'] ? (int)$row['accepted_answer_id'] : null);
        }
        return $rows;
    }

    /** Total question count matching the same filters as listQuestions(), for pagination. */
    public function countQuestions(string $q = '', ?int $tagId = null, string $status = '', string $department = ''): int
    {
        [$joinSql, $whereSql, $params] = $this->questionsQueryParts($q, $tagId, $status, $department);
        $sql = 'SELECT COUNT(*) c FROM forum_questions q JOIN users u ON u.id = q.user_id' . $joinSql . $whereSql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    /** Batch tag lookup for a set of question ids — mirrors TaskRepository::labelsForTasks(). */
    public function tagsForQuestions(array $questionIds): array
    {
        if (!$questionIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmt = $this->db->prepare("
            SELECT qt.question_id, t.id, t.name
            FROM forum_question_tags qt JOIN forum_tags t ON t.id = qt.tag_id
            WHERE qt.question_id IN ($placeholders)
            ORDER BY t.name
        ");
        $stmt->execute($questionIds);
        $byQuestion = [];
        foreach ($stmt->fetchAll() as $row) {
            $byQuestion[(int)$row['question_id']][] = ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        return $byQuestion;
    }

    public function findQuestion(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT q.*, u.name AS author_name, u.role AS author_role, u.department AS author_department, u.photo_filename AS author_photo_filename
            FROM forum_questions q JOIN users u ON u.id = q.user_id
            WHERE q.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['tags'] = $this->tagsForQuestions([$id])[$id] ?? [];
        $row['status'] = self::deriveStatus($row['accepted_answer_id'] ? (int)$row['accepted_answer_id'] : null);
        return $row;
    }

    /** Distinct departments among people who have asked a question, for the list page's filter. */
    public function departmentsWithQuestions(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT u.department FROM forum_questions q
            JOIN users u ON u.id = q.user_id
            WHERE u.department IS NOT NULL AND u.department != ''
            ORDER BY u.department
        ");
        return array_column($stmt->fetchAll(), 'department');
    }

    public function answersForQuestion(int $questionId): array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, u.name AS author_name, u.role AS author_role, u.department AS author_department, u.photo_filename AS author_photo_filename,
                (SELECT COUNT(*) FROM forum_answer_helpful h WHERE h.answer_id = a.id) AS helpful_count,
                (SELECT accepted_answer_id FROM forum_questions WHERE id = a.question_id) AS accepted_answer_id
            FROM forum_answers a JOIN users u ON u.id = a.user_id
            WHERE a.question_id = ?
        ');
        $stmt->execute([$questionId]);
        $rows = $stmt->fetchAll();

        usort($rows, function ($a, $b) {
            $aAccepted = (int)$a['id'] === (int)$a['accepted_answer_id'];
            $bAccepted = (int)$b['id'] === (int)$b['accepted_answer_id'];
            if ($aAccepted !== $bAccepted) {
                return $aAccepted ? -1 : 1;
            }
            return $b['helpful_count'] <=> $a['helpful_count'];
        });

        return $rows;
    }

    /** Marking Helpful a second time removes it — a plain toggle, no negative/downvote option.
     *  Returns the resulting state (true = now marked helpful). */
    public function toggleHelpful(int $userId, int $answerId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM forum_answer_helpful WHERE user_id = ? AND answer_id = ?');
        $stmt->execute([$userId, $answerId]);
        if ($stmt->fetch()) {
            $del = $this->db->prepare('DELETE FROM forum_answer_helpful WHERE user_id = ? AND answer_id = ?');
            $del->execute([$userId, $answerId]);
            return false;
        }
        $ins = $this->db->prepare('INSERT IGNORE INTO forum_answer_helpful (user_id, answer_id) VALUES (?, ?)');
        $ins->execute([$userId, $answerId]);
        return true;
    }

    public function helpfulCountForAnswer(int $answerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) c FROM forum_answer_helpful WHERE answer_id = ?');
        $stmt->execute([$answerId]);
        return (int)$stmt->fetch()['c'];
    }

    /** Batch lookup for a thread page's answers — [answer_id => true] for ones this user marked
     *  Helpful; answers absent from the map simply haven't been marked by this user. */
    public function myHelpfulAnswers(int $userId, array $answerIds): array
    {
        if (!$answerIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $stmt = $this->db->prepare("SELECT answer_id FROM forum_answer_helpful WHERE user_id = ? AND answer_id IN ($placeholders)");
        $stmt->execute(array_merge([$userId], $answerIds));
        $marked = [];
        foreach ($stmt->fetchAll() as $row) {
            $marked[(int)$row['answer_id']] = true;
        }
        return $marked;
    }

    /** Only the question's own author may accept an answer — no admin override.
     *  Accepting a different answer later automatically replaces the previous one, since
     *  accepted_answer_id is a single column on the question. */
    public function acceptAnswer(int $questionId, int $answerId, int $requestingUserId): bool
    {
        $question = $this->findQuestion($questionId);
        if (!$question || (int)$question['user_id'] !== $requestingUserId) {
            return false;
        }
        $answer = $this->db->prepare('SELECT id FROM forum_answers WHERE id = ? AND question_id = ?');
        $answer->execute([$answerId, $questionId]);
        if (!$answer->fetch()) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE forum_questions SET accepted_answer_id = ? WHERE id = ?');
        $stmt->execute([$answerId, $questionId]);
        $this->touchQuestion($questionId);
        return true;
    }

    public function deleteQuestion(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM forum_questions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteAnswer(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM forum_answers WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function findAnswer(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM forum_answers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allTags(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM forum_tags ORDER BY name');
        return $stmt->fetchAll();
    }

    public function createQuestionComment(int $questionId, int $userId, string $body): int
    {
        $stmt = $this->db->prepare('INSERT INTO forum_question_comments (question_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$questionId, $userId, $body]);
        return (int)$this->db->lastInsertId();
    }

    public function createAnswerComment(int $answerId, int $userId, string $body): int
    {
        $stmt = $this->db->prepare('INSERT INTO forum_answer_comments (answer_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$answerId, $userId, $body]);
        return (int)$this->db->lastInsertId();
    }

    public function commentsForQuestion(int $questionId): array
    {
        $stmt = $this->db->prepare('
            SELECT c.*, u.name AS author_name FROM forum_question_comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.question_id = ? ORDER BY c.created_at ASC
        ');
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }

    /** Batch comment lookup for a set of answer ids, so the thread page loads all comments in one query. */
    public function commentsForAnswers(array $answerIds): array
    {
        if (!$answerIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $stmt = $this->db->prepare("
            SELECT c.*, u.name AS author_name FROM forum_answer_comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.answer_id IN ($placeholders)
            ORDER BY c.created_at ASC
        ");
        $stmt->execute($answerIds);
        $byAnswer = [];
        foreach ($stmt->fetchAll() as $row) {
            $byAnswer[(int)$row['answer_id']][] = $row;
        }
        return $byAnswer;
    }

    public function findQuestionComment(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM forum_question_comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAnswerComment(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM forum_answer_comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteQuestionComment(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM forum_question_comments WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteAnswerComment(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM forum_answer_comments WHERE id = ?');
        $stmt->execute([$id]);
    }

    /* ---------- Answer attachments — mirrors TaskAttachmentRepository exactly ---------- */

    public function createAnswerAttachment(int $answerId, int $userId, string $originalFilename, string $storedFilename, int $sizeBytes, ?string $mimeType): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO forum_answer_attachments (answer_id, user_id, original_filename, stored_filename, size_bytes, mime_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$answerId, $userId, $originalFilename, $storedFilename, $sizeBytes, $mimeType]);
        return (int)$this->db->lastInsertId();
    }

    public function attachmentsForAnswer(int $answerId): array
    {
        $stmt = $this->db->prepare('
            SELECT a.*, u.name AS uploader_name
            FROM forum_answer_attachments a JOIN users u ON u.id = a.user_id
            WHERE a.answer_id = ? ORDER BY a.created_at DESC
        ');
        $stmt->execute([$answerId]);
        return $stmt->fetchAll();
    }

    /** Batch lookup for a thread page's answers — [answer_id => [attachment, ...]]. */
    public function attachmentsForAnswers(array $answerIds): array
    {
        if (!$answerIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $stmt = $this->db->prepare("
            SELECT a.*, u.name AS uploader_name
            FROM forum_answer_attachments a JOIN users u ON u.id = a.user_id
            WHERE a.answer_id IN ($placeholders)
            ORDER BY a.created_at DESC
        ");
        $stmt->execute($answerIds);
        $byAnswer = [];
        foreach ($stmt->fetchAll() as $row) {
            $byAnswer[(int)$row['answer_id']][] = $row;
        }
        return $byAnswer;
    }

    public function findAnswerAttachment(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM forum_answer_attachments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteAnswerAttachment(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM forum_answer_attachments WHERE id = ?');
        $stmt->execute([$id]);
    }
}
