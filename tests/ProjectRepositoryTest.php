<?php

use App\Repositories\ProjectRepository;

test_suite('ProjectRepository (methods added this session)', function () {
    $repo = new ProjectRepository();

    test('nextSuggestedCode returns a BEL-PRJ-### code', function () use ($repo) {
        $code = $repo->nextSuggestedCode();
        assert_true((bool)preg_match('/^BEL-PRJ-\d{3}$/', $code), "Got: $code");
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
