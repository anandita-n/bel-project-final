<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

require_login_json();

$q = trim($_GET['q'] ?? '');
$tagId = !empty($_GET['tag_id']) ? (int)$_GET['tag_id'] : null;
$sort = in_array($_GET['sort'] ?? '', ['recent', 'most_helpful', 'updated'], true) ? $_GET['sort'] : 'recent';
$status = in_array($_GET['status'] ?? '', ['open', 'solved'], true) ? $_GET['status'] : '';
$department = trim($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$repo = new ForumRepository();
$rows = $repo->listQuestions($q, $tagId, $sort, $status, $department, $page, $perPage);
$total = $repo->countQuestions($q, $tagId, $status, $department);

$results = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'title' => $r['title'],
    'author_id' => (int)$r['user_id'],
    'author_name' => $r['author_name'],
    'author_role' => $r['author_role'],
    'author_department' => $r['author_department'],
    'author_has_photo' => !empty($r['author_photo_filename']),
    'answer_count' => (int)$r['answer_count'],
    'helpful_count' => (int)$r['helpful_count'],
    'status' => $r['status'],
    'created_at' => $r['created_at'],
    'updated_at' => $r['updated_at'],
    'tags' => $r['tags'],
], $rows);

json_out([
    'results' => $results,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => max(1, (int)ceil($total / $perPage)),
]);
