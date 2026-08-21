<?php

// Integration tests for api/*.php endpoints — these hit the real running Apache server (see
// framework.php's http_test()/make_http_user() doc-comment for why: json_out() calls exit(),
// so these can't run in-process like the rest of the suite).

test_suite('API integration (asset endpoints)', function () {
    $pdo = \App\Database::connection();

    http_test('employee cannot see another employee\'s asset even by spoofing employeeId', function () use ($pdo) {
        $admin = make_http_user('API Admin One', 'BEL-API-A1', 'admin');
        $owner = make_http_user('API Owner One', 'BEL-API-O1');
        $other = make_http_user('API Other One', 'BEL-API-X1');

        $pdo->prepare("INSERT INTO assets (asset_code, name, category, assigned_to, status) VALUES (?, 'Owned Laptop', 'laptop', ?, 'assigned')")
            ->execute(['BEL-AST-APITEST1', $owner['id']]);
        $assetId = (int)$pdo->lastInsertId();

        $resp = http_get('api/assets/list.php?employeeId=' . $owner['id'], $other['cookies']);
        $codes = array_column($resp['body']['results'] ?? [], 'asset_code');

        $pdo->prepare('DELETE FROM assets WHERE id = ?')->execute([$assetId]);
        $admin['cleanup'](); $owner['cleanup'](); $other['cleanup']();

        assert_false(in_array('BEL-AST-APITEST1', $codes, true), 'Other employee must not see it, even via a spoofed employeeId param');
    });

    http_test('employee gets 403 changing an asset status', function () use ($pdo) {
        $employee = make_http_user('API Employee One', 'BEL-API-E1');
        $pdo->prepare("INSERT INTO assets (asset_code, name, category, status) VALUES ('BEL-AST-APITEST2', 'Test', 'laptop', 'available')")->execute();
        $assetId = (int)$pdo->lastInsertId();

        $resp = http_post('api/assets/status.php', ['id' => $assetId, 'status' => 'lost'], $employee['cookies']);

        $pdo->prepare('DELETE FROM assets WHERE id = ?')->execute([$assetId]);
        $employee['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('admin can change an asset status', function () use ($pdo) {
        $admin = make_http_user('API Admin Two', 'BEL-API-A2', 'admin');
        $pdo->prepare("INSERT INTO assets (asset_code, name, category, status) VALUES ('BEL-AST-APITEST3', 'Test', 'laptop', 'available')")->execute();
        $assetId = (int)$pdo->lastInsertId();

        $resp = http_post('api/assets/status.php', ['id' => $assetId, 'status' => 'lost'], $admin['cookies']);

        $status = $pdo->query("SELECT status FROM assets WHERE id = $assetId")->fetch()['status'];
        $pdo->prepare('DELETE FROM assets WHERE id = ?')->execute([$assetId]);
        $admin['cleanup']();

        assert_equals(200, $resp['status']);
        assert_equals('lost', $status);
    });

    http_test('an invalid status value is rejected with a 400', function () use ($pdo) {
        $admin = make_http_user('API Admin Three', 'BEL-API-A3', 'admin');
        $pdo->prepare("INSERT INTO assets (asset_code, name, category, status) VALUES ('BEL-AST-APITEST4', 'Test', 'laptop', 'available')")->execute();
        $assetId = (int)$pdo->lastInsertId();

        $resp = http_post('api/assets/status.php', ['id' => $assetId, 'status' => 'not_a_real_status'], $admin['cookies']);

        $pdo->prepare('DELETE FROM assets WHERE id = ?')->execute([$assetId]);
        $admin['cleanup']();

        assert_equals(400, $resp['status']);
    });

    http_test('an unauthenticated request is rejected with a 401', function () {
        $resp = http_get('api/assets/list.php', tempnam(sys_get_temp_dir(), 'bel_test_nocookie_'));
        assert_equals(401, $resp['status']);
    });
});

test_suite('API integration (forum endpoints)', function () {
    $pdo = \App\Database::connection();

    http_test('a non-author gets 403 accepting an answer', function () use ($pdo) {
        $author = make_http_user('API Forum Author One', 'BEL-API-F1');
        $stranger = make_http_user('API Forum Stranger One', 'BEL-API-F2');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API test question', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, 'answer body')")->execute([$qId, $author['id']]);
        $aId = (int)$pdo->lastInsertId();

        $resp = http_post('api/forum/accept.php', ['question_id' => $qId, 'answer_id' => $aId], $stranger['cookies']);

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup'](); $stranger['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('the question author can accept an answer', function () use ($pdo) {
        $author = make_http_user('API Forum Author Two', 'BEL-API-F3');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API test question 2', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, 'answer body')")->execute([$qId, $author['id']]);
        $aId = (int)$pdo->lastInsertId();

        $resp = http_post('api/forum/accept.php', ['question_id' => $qId, 'answer_id' => $aId], $author['cookies']);
        $accepted = $pdo->query("SELECT accepted_answer_id FROM forum_questions WHERE id = $qId")->fetch()['accepted_answer_id'];

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup']();

        assert_equals(200, $resp['status']);
        assert_equals($aId, (int)$accepted);
    });

    http_test('helpful.php toggles on then off, and only ever a single click each way', function () use ($pdo) {
        $author = make_http_user('API Forum Author Helpful1', 'BEL-API-H1A');
        $marker = make_http_user('API Forum Marker Helpful1', 'BEL-API-H1B');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API helpful test question', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, 'answer body')")->execute([$qId, $author['id']]);
        $aId = (int)$pdo->lastInsertId();

        $first = http_post('api/forum/helpful.php', ['answer_id' => $aId], $marker['cookies']);
        $second = http_post('api/forum/helpful.php', ['answer_id' => $aId], $marker['cookies']);

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup'](); $marker['cleanup']();

        assert_equals(true, $first['body']['helpful']);
        assert_equals(1, $first['body']['count']);
        assert_equals(false, $second['body']['helpful']);
        assert_equals(0, $second['body']['count']);
    });

    http_test('even an admin gets 403 accepting an answer on a question they did not ask', function () use ($pdo) {
        $author = make_http_user('API Forum Author Admin1', 'BEL-API-AD1A');
        $admin = make_http_user('API Forum Admin Non-Overrider', 'BEL-API-AD1B', 'admin');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API admin-accept test question', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, 'answer body')")->execute([$qId, $author['id']]);
        $aId = (int)$pdo->lastInsertId();

        $resp = http_post('api/forum/accept.php', ['question_id' => $qId, 'answer_id' => $aId], $admin['cookies']);

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup'](); $admin['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('a stranger gets 403 uploading an attachment to someone else\'s answer', function () use ($pdo) {
        $author = make_http_user('API Forum Author Attach1', 'BEL-API-ATA1');
        $stranger = make_http_user('API Forum Stranger Attach1', 'BEL-API-ATA2');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API attach test question', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_answers (question_id, user_id, body) VALUES (?, ?, 'answer body')")->execute([$qId, $author['id']]);
        $aId = (int)$pdo->lastInsertId();

        // No file attached — the author-check runs before the "no file uploaded" check, so this
        // still exercises the permission branch without needing a real multipart upload.
        $resp = http_post('api/forum/attachments.php', ['action' => 'upload', 'answer_id' => $aId], $stranger['cookies']);

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup'](); $stranger['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('a non-owner non-admin gets 403 deleting someone else\'s comment', function () use ($pdo) {
        $author = make_http_user('API Forum Author Three', 'BEL-API-F5');
        $stranger = make_http_user('API Forum Stranger Two', 'BEL-API-F6');
        $pdo->prepare("INSERT INTO forum_questions (user_id, title, body) VALUES (?, 'API test question 3', 'body')")->execute([$author['id']]);
        $qId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO forum_question_comments (question_id, user_id, body) VALUES (?, ?, 'a comment')")->execute([$qId, $author['id']]);
        $commentId = (int)$pdo->lastInsertId();

        $resp = http_post('api/forum/delete.php', ['type' => 'question_comment', 'id' => $commentId], $stranger['cookies']);

        $pdo->prepare('DELETE FROM forum_questions WHERE id = ?')->execute([$qId]);
        $author['cleanup'](); $stranger['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('hierarchy.php returns found=false for a query that matches nobody', function () use ($pdo) {
        $user = make_http_user('API Hierarchy User One', 'BEL-API-H1');
        $resp = http_get('api/employees/hierarchy.php?q=NoSuchEmployeeXYZ999', $user['cookies']);
        $user['cleanup']();
        assert_equals(false, $resp['body']['found']);
    });
});

test_suite('API integration (project archive/restore)', function () {
    $pdo = \App\Database::connection();

    http_test('admin can archive any project', function () use ($pdo) {
        $admin = make_http_user('API Archive Admin One', 'BEL-API-AR1', 'admin');
        $mgr = make_http_user('API Archive Manager One', 'BEL-API-AR2', 'manager');
        $projectId = make_project('BEL-PRJ-APIAR1', 'API Archive Project 1', $mgr['id']);

        $resp = http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $admin['cookies']);
        $archivedAt = $pdo->query("SELECT archived_at FROM projects WHERE id = $projectId")->fetch()['archived_at'];

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $admin['cleanup'](); $mgr['cleanup']();

        assert_equals(200, $resp['status']);
        assert_true($archivedAt !== null, 'archived_at should be set');
    });

    http_test('a project manager can archive their own project', function () use ($pdo) {
        $mgr = make_http_user('API Archive Manager Two', 'BEL-API-AR3', 'manager');
        $projectId = make_project('BEL-PRJ-APIAR2', 'API Archive Project 2', $mgr['id']);

        $resp = http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $mgr['cookies']);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $mgr['cleanup']();

        assert_equals(200, $resp['status']);
    });

    http_test('a manager cannot archive another manager\'s project', function () use ($pdo) {
        $ownerMgr = make_http_user('API Archive Owner Manager', 'BEL-API-AR4', 'manager');
        $otherMgr = make_http_user('API Archive Other Manager', 'BEL-API-AR5', 'manager');
        $projectId = make_project('BEL-PRJ-APIAR3', 'API Archive Project 3', $ownerMgr['id']);

        $resp = http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $otherMgr['cookies']);
        $archivedAt = $pdo->query("SELECT archived_at FROM projects WHERE id = $projectId")->fetch()['archived_at'];

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $ownerMgr['cleanup'](); $otherMgr['cleanup']();

        assert_equals(403, $resp['status']);
        assert_null($archivedAt);
    });

    http_test('a plain employee cannot archive a project', function () use ($pdo) {
        $mgr = make_http_user('API Archive Manager Three', 'BEL-API-AR6', 'manager');
        $emp = make_http_user('API Archive Employee One', 'BEL-API-AR7');
        $projectId = make_project('BEL-PRJ-APIAR4', 'API Archive Project 4', $mgr['id']);

        $resp = http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $emp['cookies']);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $mgr['cleanup'](); $emp['cleanup']();

        assert_equals(403, $resp['status']);
    });

    http_test('an unauthenticated request to archive is rejected with 401', function () use ($pdo) {
        $mgr = make_http_user('API Archive Manager Four', 'BEL-API-AR8', 'manager');
        $projectId = make_project('BEL-PRJ-APIAR5', 'API Archive Project 5', $mgr['id']);

        $resp = http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], tempnam(sys_get_temp_dir(), 'bel_test_nocookie_'));

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $mgr['cleanup']();

        assert_equals(401, $resp['status']);
    });

    http_test('archiving does not delete tasks, and task/member/defect creation is blocked while archived', function () use ($pdo) {
        $admin = make_http_user('API Archive Admin Two', 'BEL-API-AR9', 'admin');
        $mgr = make_http_user('API Archive Manager Five', 'BEL-API-AR10', 'manager');
        $emp = make_http_user('API Archive Employee Two', 'BEL-API-AR11');
        $projectId = make_project('BEL-PRJ-APIAR6', 'API Archive Project 6', $mgr['id']);
        $taskId = (new \App\Repositories\TaskRepository())->create([
            'project_id' => $projectId, 'title' => 'Survives archiving', 'description' => '',
            'priority' => 'medium', 'created_by' => $mgr['id'],
        ]);

        http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $admin['cookies']);

        $taskStillThere = (int)$pdo->query("SELECT COUNT(*) c FROM tasks WHERE id = $taskId")->fetch()['c'];
        $createTaskResp = http_post('api/projects/tasks.php', ['action' => 'create', 'project_id' => $projectId, 'title' => 'New task'], $mgr['cookies']);
        $createDefectResp = http_post('api/projects/defects.php', ['action' => 'create', 'project_id' => $projectId, 'title' => 'New defect', 'code' => 'APIAR6-D1', 'severity' => 'minor'], $mgr['cookies']);
        $addMemberResp = http_post('api/projects/members.php', ['action' => 'add', 'project_id' => $projectId, 'user_id' => $emp['id']], $mgr['cookies']);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $admin['cleanup'](); $mgr['cleanup'](); $emp['cleanup']();

        assert_equals(1, $taskStillThere, 'existing task must survive archiving');
        assert_equals(403, $createTaskResp['status']);
        assert_equals(403, $createDefectResp['status']);
        assert_equals(403, $addMemberResp['status']);
    });

    http_test('archived project is excluded from the active list and included in the archived list', function () use ($pdo) {
        $admin = make_http_user('API Archive Admin Three', 'BEL-API-AR12', 'admin');
        $mgr = make_http_user('API Archive Manager Six', 'BEL-API-AR13', 'manager');
        $projectId = make_project('BEL-PRJ-APIAR7', 'API Archive Project 7', $mgr['id']);
        http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $admin['cookies']);

        $activeList = http_get('api/projects/list.php?q=' . urlencode('API Archive Project 7'), $admin['cookies']);
        $archivedList = http_get('api/projects/list.php?archived=1&q=' . urlencode('API Archive Project 7'), $admin['cookies']);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $admin['cleanup'](); $mgr['cleanup']();

        $activeIds = array_column($activeList['body']['results'] ?? [], 'id');
        $archivedIds = array_column($archivedList['body']['results'] ?? [], 'id');
        assert_true(!in_array($projectId, $activeIds, true), 'archived project must not appear in the active list');
        assert_true(in_array($projectId, $archivedIds, true), 'archived project must appear in the archived list');
    });

    http_test('admin can restore an archived project and it becomes active again', function () use ($pdo) {
        $admin = make_http_user('API Restore Admin One', 'BEL-API-RS1', 'admin');
        $mgr = make_http_user('API Restore Manager One', 'BEL-API-RS2', 'manager');
        $projectId = make_project('BEL-PRJ-APIRS1', 'API Restore Project 1', $mgr['id']);
        http_post('api/projects/status.php', ['action' => 'archive', 'project_id' => $projectId], $admin['cookies']);

        $resp = http_post('api/projects/status.php', ['action' => 'restore', 'project_id' => $projectId], $admin['cookies']);
        $archivedAt = $pdo->query("SELECT archived_at FROM projects WHERE id = $projectId")->fetch()['archived_at'];
        // Task creation should work again post-restore.
        $createTaskResp = http_post('api/projects/tasks.php', ['action' => 'create', 'project_id' => $projectId, 'title' => 'Post-restore task'], $mgr['cookies']);

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $admin['cleanup'](); $mgr['cleanup']();

        assert_equals(200, $resp['status']);
        assert_null($archivedAt);
        assert_equals(200, $createTaskResp['status']);
    });

    http_test('permanent delete is blocked when the project has activity, even for admin', function () use ($pdo) {
        $admin = make_http_user('API Delete Admin One', 'BEL-API-DL1', 'admin');
        $mgr = make_http_user('API Delete Manager One', 'BEL-API-DL2', 'manager');
        $projectId = make_project('BEL-PRJ-APIDL1', 'API Delete Project 1', $mgr['id']);
        (new \App\Repositories\TaskRepository())->create([
            'project_id' => $projectId, 'title' => 'Blocks permanent delete', 'description' => '',
            'priority' => 'medium', 'created_by' => $mgr['id'],
        ]);

        $resp = http_post('api/projects/status.php', ['action' => 'delete', 'project_id' => $projectId], $admin['cookies']);
        $stillExists = (int)$pdo->query("SELECT COUNT(*) c FROM projects WHERE id = $projectId")->fetch()['c'];

        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$projectId]);
        $admin['cleanup'](); $mgr['cleanup']();

        assert_equals(400, $resp['status']);
        assert_equals(1, $stillExists, 'project with activity must not be permanently deleted');
    });

    http_test('permanent delete succeeds for a genuinely empty project, admin only', function () use ($pdo) {
        $admin = make_http_user('API Delete Admin Two', 'BEL-API-DL3', 'admin');
        $mgr = make_http_user('API Delete Manager Two', 'BEL-API-DL4', 'manager');
        $projectId = make_project('BEL-PRJ-APIDL2', 'API Delete Project 2', $mgr['id']);

        $managerAttempt = http_post('api/projects/status.php', ['action' => 'delete', 'project_id' => $projectId], $mgr['cookies']);
        $adminAttempt = http_post('api/projects/status.php', ['action' => 'delete', 'project_id' => $projectId], $admin['cookies']);
        $stillExists = (int)$pdo->query("SELECT COUNT(*) c FROM projects WHERE id = $projectId")->fetch()['c'];

        $admin['cleanup'](); $mgr['cleanup']();

        assert_equals(403, $managerAttempt['status'], 'a manager must never be able to permanently delete');
        assert_equals(200, $adminAttempt['status']);
        assert_equals(0, $stillExists, 'empty project should be permanently deletable by admin');
    });
});
