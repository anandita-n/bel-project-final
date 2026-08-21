<?php

use App\Repositories\ProjectRepository;

test_suite('ProjectRepository (methods added this session)', function () {
    $repo = new ProjectRepository();

    test('nextSuggestedCode returns a PRJ### code, one past the highest existing one', function () use ($repo) {
        $code = $repo->nextSuggestedCode();
        assert_true((bool)preg_match('/^PRJ\d{3,}$/', $code), "Got: $code");

        $mgr = make_user('Suggested Code Manager', 'BEL-SUG01', 'manager');
        $projectId = make_project('PRJ997', 'Suggested Code Project', $mgr);
        assert_equals('PRJ998', $repo->nextSuggestedCode());
        $repo->delete($projectId);
    });

    test('findById includes the manager\'s telephone', function () use ($repo) {
        $mgr = make_user('Tele Manager', 'BEL-TEL01', 'manager');
        \App\Database::connection()->prepare('UPDATE users SET telephone = ? WHERE id = ?')->execute(['555-0100', $mgr]);
        $projectId = make_project('BEL-PRJ-TEL1', 'Telephone Test Project', $mgr);

        $project = $repo->findById($projectId);
        assert_equals('555-0100', $project['manager_telephone']);
    });

    test('addMember stores the given permission_level', function () use ($repo) {
        $mgr = make_user('Member Manager', 'BEL-MEM01', 'manager');
        $emp = make_user('Member Employee', 'BEL-MEM02');
        $projectId = make_project('BEL-PRJ-MEM1', 'Member Test Project', $mgr);

        $repo->addMember($projectId, $emp, 'Developer', 'lead');
        $members = $repo->members($projectId);
        assert_equals('lead', $members[0]['permission_level']);
        assert_equals('Developer', $members[0]['role_in_project']);
    });

    test('addMember defaults permission_level to member when omitted', function () use ($repo) {
        $mgr = make_user('Member Manager Two', 'BEL-MEM03', 'manager');
        $emp = make_user('Member Employee Two', 'BEL-MEM04');
        $projectId = make_project('BEL-PRJ-MEM2', 'Member Test Project 2', $mgr);

        $repo->addMember($projectId, $emp, 'Tester');
        assert_equals('member', $repo->members($projectId)[0]['permission_level']);
    });

    test('updateMember changes role and permission for an existing member', function () use ($repo) {
        $mgr = make_user('Member Manager Three', 'BEL-MEM05', 'manager');
        $emp = make_user('Member Employee Three', 'BEL-MEM06');
        $projectId = make_project('BEL-PRJ-MEM3', 'Member Test Project 3', $mgr);

        $repo->addMember($projectId, $emp, 'Tester', 'member');
        $repo->updateMember($projectId, $emp, 'Lead Tester', 'manager');

        $members = $repo->members($projectId);
        assert_equals('Lead Tester', $members[0]['role_in_project']);
        assert_equals('manager', $members[0]['permission_level']);
    });

    test('removeMember removes exactly that member', function () use ($repo) {
        $mgr = make_user('Member Manager Four', 'BEL-MEM07', 'manager');
        $empKeep = make_user('Keep Member', 'BEL-MEM08');
        $empRemove = make_user('Remove Member', 'BEL-MEM09');
        $projectId = make_project('BEL-PRJ-MEM4', 'Member Test Project 4', $mgr);

        $repo->addMember($projectId, $empKeep, 'Dev');
        $repo->addMember($projectId, $empRemove, 'Dev');
        $repo->removeMember($projectId, $empRemove);

        $remaining = array_column($repo->members($projectId), 'id');
        assert_equals([$empKeep], $remaining);
    });

    test('codeExists is true only after the project is created', function () use ($repo) {
        assert_false($repo->codeExists('BEL-PRJ-UNIQX'));
        $mgr = make_user('Code Manager', 'BEL-COD01', 'manager');
        make_project('BEL-PRJ-UNIQX', 'Unique Code Project', $mgr);
        assert_true($repo->codeExists('BEL-PRJ-UNIQX'));
    });

    test('archive sets archived_at/archived_by/archive_reason without touching other data', function () use ($repo) {
        $mgr = make_user('Archive Manager', 'BEL-ARC01', 'manager');
        $admin = make_user('Archive Admin', 'BEL-ARC02', 'admin');
        $emp = make_user('Archive Member', 'BEL-ARC03');
        $projectId = make_project('BEL-PRJ-ARC1', 'Archive Test Project', $mgr);
        $repo->addMember($projectId, $emp, 'Developer');
        $taskId = (new \App\Repositories\TaskRepository())->create([
            'project_id' => $projectId, 'title' => 'Should survive archiving', 'description' => '',
            'priority' => 'medium', 'created_by' => $mgr,
        ]);

        $repo->archive($projectId, $admin, 'No longer needed');

        $project = $repo->findById($projectId);
        assert_true($project['archived_at'] !== null, 'archived_at should be set');
        assert_equals($admin, (int)$project['archived_by']);
        assert_equals('No longer needed', $project['archive_reason']);
        // Status/name/members/tasks are untouched by archiving.
        assert_equals('active', $project['status']);
        assert_equals(1, count($repo->members($projectId)));
        $db = \App\Database::connection();
        $stmt = $db->prepare('SELECT COUNT(*) c FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        assert_equals(1, (int)$stmt->fetch()['c']);
    });

    test('restore clears archived_at/archived_by/archive_reason', function () use ($repo) {
        $mgr = make_user('Restore Manager', 'BEL-RST01', 'manager');
        $admin = make_user('Restore Admin', 'BEL-RST02', 'admin');
        $projectId = make_project('BEL-PRJ-RST1', 'Restore Test Project', $mgr);

        $repo->archive($projectId, $admin, 'temporary');
        $repo->restore($projectId);

        $project = $repo->findById($projectId);
        assert_null($project['archived_at']);
        assert_null($project['archived_by']);
        assert_null($project['archive_reason']);
    });

    test('archived projects are excluded from listForUser by default and included with $archived=true', function () use ($repo) {
        $admin = make_user('List Admin', 'BEL-LST01', 'admin');
        $mgr = make_user('List Manager', 'BEL-LST02', 'manager');
        $active = make_project('BEL-PRJ-LST1', 'Active Listing Project', $mgr);
        $archivedId = make_project('BEL-PRJ-LST2', 'Archived Listing Project', $mgr);
        $repo->archive($archivedId, $admin, null);

        $activeIds = array_column($repo->listForUser(['id' => $admin, 'role' => 'admin'], '', '', ''), 'id');
        assert_true(in_array($active, $activeIds, true), 'active project should appear in default listing');
        assert_true(!in_array($archivedId, $activeIds, true), 'archived project should NOT appear in default listing');

        $archivedIds = array_column($repo->listForUser(['id' => $admin, 'role' => 'admin'], '', '', '', 1, 0, true), 'id');
        assert_true(in_array($archivedId, $archivedIds, true), 'archived project should appear in archived listing');
        assert_true(!in_array($active, $archivedIds, true), 'active project should NOT appear in archived listing');
    });

    test('activityCounts reflects tasks/defects/documents/members', function () use ($repo) {
        $mgr = make_user('Activity Manager', 'BEL-ACT01', 'manager');
        $emp = make_user('Activity Member', 'BEL-ACT02');
        $emptyProjectId = make_project('BEL-PRJ-ACT1', 'Empty Project', $mgr);
        $busyProjectId = make_project('BEL-PRJ-ACT2', 'Busy Project', $mgr);
        $repo->addMember($busyProjectId, $emp, 'Developer');
        (new \App\Repositories\TaskRepository())->create([
            'project_id' => $busyProjectId, 'title' => 'A task', 'description' => '',
            'priority' => 'medium', 'created_by' => $mgr,
        ]);

        assert_equals(0, array_sum($repo->activityCounts($emptyProjectId)));
        assert_true(array_sum($repo->activityCounts($busyProjectId)) > 0, 'busy project should have nonzero activity');
    });

    test('managedBy excludes archived projects', function () use ($repo) {
        $admin = make_user('ManagedBy Admin', 'BEL-MGB01', 'admin');
        $mgr = make_user('ManagedBy Manager', 'BEL-MGB02', 'manager');
        $projectId = make_project('BEL-PRJ-MGB1', 'ManagedBy Project', $mgr);

        assert_equals(1, count($repo->managedBy($mgr)));
        $repo->archive($projectId, $admin, null);
        assert_equals(0, count($repo->managedBy($mgr)));
    });

    test('delete removes the project and cascades to its members and tasks', function () use ($repo) {
        $mgr = make_user('Delete Manager', 'BEL-DEL01', 'manager');
        $emp = make_user('Delete Member', 'BEL-DEL02');
        $projectId = make_project('BEL-PRJ-DEL1', 'Delete Test Project', $mgr);
        $repo->addMember($projectId, $emp, 'Developer');
        $taskId = (new \App\Repositories\TaskRepository())->create([
            'project_id' => $projectId, 'title' => 'Cascade check', 'description' => '',
            'priority' => 'medium', 'created_by' => $mgr,
        ]);

        $repo->delete($projectId);

        assert_null($repo->findById($projectId));
        $db = \App\Database::connection();
        $stmt = $db->prepare('SELECT COUNT(*) c FROM project_members WHERE project_id = ?');
        $stmt->execute([$projectId]);
        assert_equals(0, (int)$stmt->fetch()['c']);
        $stmt = $db->prepare('SELECT COUNT(*) c FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        assert_equals(0, (int)$stmt->fetch()['c']);
    });
});
