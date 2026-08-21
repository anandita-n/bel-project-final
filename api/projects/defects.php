<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\DefectRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$defects = new DefectRepository();

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}

$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];
// Every action in this file mutates defect data — archived projects are read-only.
require_project_active($project);

if ($action === 'create') {
    if (!$canManage) {
        json_error('Not permitted to add defects on this project.', 403);
    }
    $title = trim($body['title'] ?? '');
    $code = trim($body['code'] ?? '');
    if ($title === '' || $code === '') {
        json_error('Defect ID and title are required.');
    }
    if ($defects->codeExists($code)) {
        json_error('This defect ID is already in use.');
    }
    $severity = in_array($body['severity'] ?? '', array_keys(DefectRepository::SEVERITIES), true) ? $body['severity'] : 'minor';

    $defectId = $defects->create([
        'project_id' => $projectId,
        'code' => $code,
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'severity' => $severity,
        'assigned_to' => !empty($body['assigned_to']) ? (int)$body['assigned_to'] : null,
        'reported_by' => (int)$u['id'],
    ]);

    $defect = $defects->findWithNames($defectId);
    json_out(['ok' => true, 'defect' => $defect]);
} elseif ($action === 'update') {
    if (!$canManage) {
        json_error('Not permitted to edit defects on this project.', 403);
    }
    $defectId = (int)($body['defect_id'] ?? 0);
    $title = trim($body['title'] ?? '');
    if (!$defectId || $title === '') {
        json_error('Defect title is required.');
    }
    if (!$defects->find($defectId, $projectId)) {
        json_error('Defect not found.', 404);
    }
    $severity = in_array($body['severity'] ?? '', array_keys(DefectRepository::SEVERITIES), true) ? $body['severity'] : 'minor';

    $defects->update($defectId, $projectId, [
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'severity' => $severity,
        'assigned_to' => !empty($body['assigned_to']) ? (int)$body['assigned_to'] : null,
    ]);

    $defect = $defects->findWithNames($defectId);
    json_out(['ok' => true, 'defect' => $defect]);
} elseif ($action === 'update_status') {
    $defectId = (int)($body['defect_id'] ?? 0);
    $status = $body['status'] ?? '';
    if (!$defectId || !in_array($status, array_keys(DefectRepository::STATUSES), true)) {
        json_error('Invalid defect or status.');
    }
    $defect = $defects->find($defectId, $projectId);
    if (!$defect) {
        json_error('Defect not found.', 404);
    }
    $isAssignee = (int)($defect['assigned_to'] ?? 0) === (int)$u['id'];
    if (!$canManage && !$isAssignee) {
        json_error('Not permitted to update this defect.', 403);
    }
    $defects->updateStatus($defectId, $status);
    json_out(['ok' => true]);
} elseif ($action === 'delete') {
    if (!$canManage) {
        json_error('Not permitted to delete defects on this project.', 403);
    }
    $defectId = (int)($body['defect_id'] ?? 0);
    if (!$defectId) {
        json_error('Missing defect id.');
    }
    $defects->delete($defectId, $projectId);
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
