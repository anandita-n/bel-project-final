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
