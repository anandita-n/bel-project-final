<?php

// render_*() functions build HTML strings — asserted here via substring/structure checks
// rather than full DOM parsing, which keeps these tests fast and easy to read.
require_once __DIR__ . '/../includes/helpers.php';

test_suite('render_*() HTML helpers', function () {
    test('render_asset_row escapes an XSS attempt in the asset name', function () {
        $asset = [
            'id' => 1, 'asset_code' => 'BEL-AST-001', 'name' => '<script>alert(1)</script>',
            'category' => 'laptop', 'serial_number' => null, 'department' => null,
            'assigned_to' => null, 'assignee_name' => null, 'status' => 'available',
            'purchase_date' => null, 'warranty_expiry' => null,
        ];
        $html = render_asset_row($asset, false);
        assert_false(str_contains($html, '<script>'), 'Raw script tag must not appear in output');
        assert_true(str_contains($html, '&lt;script&gt;'), 'Name should be htmlspecialchars-escaped');
    });

    test('render_asset_row shows the Actions column only when isAdmin is true', function () {
        $asset = ['id' => 1, 'asset_code' => 'X', 'name' => 'N', 'category' => 'laptop', 'serial_number' => null, 'department' => null, 'assigned_to' => null, 'assignee_name' => null, 'status' => 'available'];
        assert_true(str_contains(render_asset_row($asset, true), 'asset-row-kebab'));
        assert_false(str_contains(render_asset_row($asset, false), 'asset-row-kebab'));
    });

    test('render_asset_row maps unknown category/status keys through their human labels', function () {
        $asset = ['id' => 1, 'asset_code' => 'X', 'name' => 'N', 'category' => 'laptop', 'serial_number' => null, 'department' => null, 'assigned_to' => null, 'assignee_name' => null, 'status' => 'under_repair'];
        $html = render_asset_row($asset, false);
        assert_true(str_contains($html, 'Under Repair'));
        assert_true(str_contains($html, 'dir-badge-under_repair'), 'under_repair should map to the dir-badge-under_repair colour class');
    });

    test('render_avatar_stack shows Unassigned for an empty list', function () {
        assert_true(str_contains(render_avatar_stack([]), 'Unassigned'));
    });

    test('render_avatar_stack renders one avatar per person up to the max', function () {
        $people = [
            ['id' => 1, 'name' => 'Alice A', 'role' => 'manager'],
            ['id' => 2, 'name' => 'Bob B', 'role' => 'employee'],
        ];
        $html = render_avatar_stack($people, 4);
        assert_equals(2, substr_count($html, 'class="avatar avatar-sm'));
        assert_false(str_contains($html, 'avatar-stack-more'), 'No overflow bubble when under the max');
    });

    test('render_avatar_stack shows a "+N" overflow bubble beyond the max', function () {
        $people = [
            ['id' => 1, 'name' => 'A', 'role' => 'employee'],
            ['id' => 2, 'name' => 'B', 'role' => 'employee'],
            ['id' => 3, 'name' => 'C', 'role' => 'employee'],
        ];
        $html = render_avatar_stack($people, 2);
        assert_true(str_contains($html, 'avatar-stack-more'));
        assert_true(str_contains($html, '+1'));
    });

    test('render_due_badge marks a past due date as late unless the task is done', function () {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        assert_true(str_contains(render_due_badge($yesterday, 'in_progress'), 'due-badge-late'));
        assert_false(str_contains(render_due_badge($yesterday, 'done'), 'due-badge-late'));
    });

    test('render_due_badge marks today as due-badge-today', function () {
        assert_true(str_contains(render_due_badge(date('Y-m-d'), 'todo'), 'due-badge-today'));
    });

    test('render_due_badge shows "No date" when there is no due date', function () {
        assert_true(str_contains(render_due_badge(null, 'todo'), 'No date'));
    });

    test('render_forum_status_badge shows Open for open and a check for solved', function () {
        assert_true(str_contains(render_forum_status_badge('open'), 'open'));
        assert_false(str_contains(render_forum_status_badge('open'), 'solved'));
        assert_true(str_contains(render_forum_status_badge('solved'), 'solved'));
        assert_true(str_contains(render_forum_status_badge('solved'), '&check;'));
    });

    test('render_forum_row shows tag chips when tags are present', function () {
        $q = ['id' => 1, 'title' => 'A question', 'status' => 'open', 'answer_count' => 1,
              'author_name' => 'Jane Doe', 'author_role' => 'employee', 'author_department' => 'Engineering', 'created_at' => '2026-01-01 00:00:00',
              'tags' => [['id' => 1, 'name' => 'php']]];
        $html = render_forum_row($q);
        assert_true(str_contains($html, 'forum-tag-list'));
        assert_true(str_contains($html, '>php<'));
    });

    test('render_forum_row omits the tag list entirely when there are no tags', function () {
        $q = ['id' => 1, 'title' => 'A question', 'status' => 'open', 'answer_count' => 0,
              'author_name' => 'Jane Doe', 'author_role' => 'employee', 'author_department' => null, 'created_at' => '2026-01-01 00:00:00', 'tags' => []];
        assert_false(str_contains(render_forum_row($q), 'forum-tag-list'));
    });

    test('render_forum_row shows the Solved badge only when status is solved', function () {
        $base = ['id' => 1, 'title' => 'Q', 'answer_count' => 0, 'author_name' => 'Jane', 'author_role' => 'employee', 'author_department' => null, 'created_at' => '2026-01-01 00:00:00', 'tags' => []];
        assert_true(str_contains(render_forum_row(['status' => 'solved'] + $base), 'solved'));
        assert_false(str_contains(render_forum_row(['status' => 'open'] + $base), 'forum-status-badge solved'));
    });

    test('render_forum_answer shows the accepted banner and class only when accepted', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => 'Engineering',
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 0, 'user_id' => 1, 'accepted_answer_id' => 5];
        $html = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 1]);
        assert_true(str_contains($html, 'forum-answer-accepted'));
        assert_true(str_contains($html, 'forum-accepted-badge'));
    });

    test('render_forum_answer hides the accepted banner when not accepted', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 0, 'user_id' => 1, 'accepted_answer_id' => 999];
        $html = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 1]);
        assert_false(str_contains($html, 'forum-answer-accepted'));
        assert_false(str_contains($html, 'forum-accepted-badge'));
    });

    test('render_forum_answer shows the Helpful button with its count', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 8, 'user_id' => 1, 'accepted_answer_id' => null];
        $html = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 1]);
        assert_true(str_contains($html, 'forum-helpful-btn'));
        assert_true(str_contains($html, '(8)'));
    });

    test('render_forum_answer marks the Helpful button active when isHelpful is true', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 1, 'user_id' => 1, 'accepted_answer_id' => null];
        $active = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 1], true);
        $inactive = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 1], false);
        assert_true(str_contains($active, 'forum-helpful-btn active'));
        assert_false(str_contains($inactive, 'forum-helpful-btn active'));
    });

    test('render_forum_answer shows the Accept toggle for the question author, accepted or not', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 0, 'user_id' => 1, 'accepted_answer_id' => null];
        $asAuthor = render_forum_answer($answer, 10, true, ['role' => 'employee', 'id' => 2]);
        $asStranger = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 3]);
        assert_true(str_contains($asAuthor, 'forum-accept-toggle'));
        assert_false(str_contains($asStranger, 'forum-accept-toggle'));

        $answer['accepted_answer_id'] = 5;
        $asAuthorAccepted = render_forum_answer($answer, 10, true, ['role' => 'employee', 'id' => 2]);
        assert_true(str_contains($asAuthorAccepted, 'forum-accept-toggle active'), 'toggle stays visible (and marked active) once accepted, so the author can undo it');
    });

    test('render_forum_answer hides Accept toggle for an admin who is not the question author', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 0, 'user_id' => 1, 'accepted_answer_id' => null];
        $asAdmin = render_forum_answer($answer, 10, false, ['role' => 'admin', 'id' => 99]);
        assert_false(str_contains($asAdmin, 'forum-accept-toggle'), 'Accept is strictly author-only, admins included');
    });

    test('render_forum_answer shows Delete only for the answer\'s own author or an admin', function () {
        $answer = ['id' => 5, 'body' => 'text', 'author_name' => 'A', 'author_role' => 'employee', 'author_department' => null,
                   'created_at' => '2026-01-01 00:00:00', 'helpful_count' => 0, 'user_id' => 7, 'accepted_answer_id' => null];
        $asOwner = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 7]);
        $asAdmin = render_forum_answer($answer, 10, false, ['role' => 'admin', 'id' => 99]);
        $asStranger = render_forum_answer($answer, 10, false, ['role' => 'employee', 'id' => 8]);
        assert_true(str_contains($asOwner, 'forum-delete-link'));
        assert_true(str_contains($asAdmin, 'forum-delete-link'));
        assert_false(str_contains($asStranger, 'forum-delete-link'));
    });

    test('render_forum_attachments shows the remove button only for the uploader or an admin', function () {
        $attachments = [['id' => 1, 'original_filename' => 'spec.pdf', 'size_bytes' => 2048, 'uploader_name' => 'Jane', 'user_id' => 5]];
        $asOwner = render_forum_attachments($attachments, 10, false, ['role' => 'employee', 'id' => 5]);
        $asStranger = render_forum_attachments($attachments, 10, false, ['role' => 'employee', 'id' => 6]);
        assert_true(str_contains($asOwner, 'forum-attachment-remove'));
        assert_false(str_contains($asStranger, 'forum-attachment-remove'));
    });

    test('render_forum_attachments shows the upload control only when canUpload is true', function () {
        $html = render_forum_attachments([], 10, true, ['role' => 'employee', 'id' => 5]);
        assert_true(str_contains($html, 'forum-attach-input'));
        assert_false(str_contains(render_forum_attachments([], 10, false, ['role' => 'employee', 'id' => 5]), 'forum-attach-input'));
    });

    test('render_document_row shows the remove button only when canManage is true', function () {
        $doc = ['id' => 1, 'original_filename' => 'spec.pdf', 'size_bytes' => 2048, 'uploader_name' => 'Jane', 'created_at' => '2026-01-01 00:00:00'];
        assert_true(str_contains(render_document_row($doc, 9, true), 'document-remove'));
        assert_false(str_contains(render_document_row($doc, 9, false), 'document-remove'));
    });
});
