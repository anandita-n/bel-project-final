<?php

use App\Repositories\TaskRepository;

test_suite('TaskRepository (multi-assignee)', function () {
    $repo = new TaskRepository();

    test('create() with assignees sets task_assignees and mirrors the first id into assigned_to', function () use ($repo) {
        $mgr = make_user('Multi Manager', 'BEL-MA01', 'manager');
        $empA = make_user('Multi Assignee A', 'BEL-MA02');
        $empB = make_user('Multi Assignee B', 'BEL-MA03');
        $projectId = make_project('BEL-PRJ-MA1', 'Multi Assignee Project', $mgr);

        $taskId = $repo->create([
            'project_id' => $projectId,
            'title' => 'Multi-assignee task',
            'description' => '',
            'priority' => 'medium',
            'created_by' => $mgr,
            'assignees' => [$empA, $empB],
        ]);

        $assignees = $repo->assigneesForTasks([$taskId])[$taskId];
        assert_count(2, $assignees);
        assert_equals([$empA, $empB], array_column($assignees, 'id'));

        $task = $repo->find($taskId, $projectId);
        assert_equals($empA, (int)$task['assigned_to']);
    });

    test('create() without assignees falls back to the single legacy assigned_to field', function () use ($repo) {
        $mgr = make_user('Legacy Manager', 'BEL-LA01', 'manager');
        $emp = make_user('Legacy Assignee', 'BEL-LA02');
        $projectId = make_project('BEL-PRJ-LA1', 'Legacy Assignee Project', $mgr);

        $taskId = $repo->create([
            'project_id' => $projectId,
            'title' => 'Legacy task',
            'description' => '',
            'priority' => 'medium',
            'created_by' => $mgr,
            'assigned_to' => $emp,
        ]);

        $assignees = $repo->assigneesForTasks([$taskId])[$taskId];
        assert_count(1, $assignees);
        assert_equals($emp, $assignees[0]['id']);
    });

    test('setTaskAssignees replaces the full list and updates the primary assigned_to', function () use ($repo) {
        $mgr = make_user('Set Manager', 'BEL-SA01', 'manager');
        $empA = make_user('Set Assignee A', 'BEL-SA02');
        $empB = make_user('Set Assignee B', 'BEL-SA03');
        $projectId = make_project('BEL-PRJ-SA1', 'Set Assignees Project', $mgr);
        $taskId = $repo->create(['project_id' => $projectId, 'title' => 'T', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);

        $repo->setTaskAssignees($taskId, [$empA, $empB]);
        assert_count(2, $repo->assigneesForTasks([$taskId])[$taskId]);
        assert_equals($empA, (int)$repo->find($taskId, $projectId)['assigned_to']);

        $repo->setTaskAssignees($taskId, [$empB]);
        $assignees = $repo->assigneesForTasks([$taskId])[$taskId];
        assert_count(1, $assignees);
        assert_equals($empB, $assignees[0]['id']);
        assert_equals($empB, (int)$repo->find($taskId, $projectId)['assigned_to']);
    });

    test('setTaskAssignees with an empty array clears assignees and assigned_to', function () use ($repo) {
        $mgr = make_user('Clear Manager', 'BEL-CA01', 'manager');
        $emp = make_user('Clear Assignee', 'BEL-CA02');
        $projectId = make_project('BEL-PRJ-CA1', 'Clear Assignees Project', $mgr);
        $taskId = $repo->create(['project_id' => $projectId, 'title' => 'T', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$emp]]);

        $repo->setTaskAssignees($taskId, []);
        assert_count(0, $repo->assigneesForTasks([$taskId])[$taskId] ?? []);
        assert_null($repo->find($taskId, $projectId)['assigned_to']);
    });

    test('isAssignee is true for every assignee, not just the primary one', function () use ($repo) {
        $mgr = make_user('Is Manager', 'BEL-IA01', 'manager');
        $empA = make_user('Is Assignee A', 'BEL-IA02');
        $empB = make_user('Is Assignee B', 'BEL-IA03');
        $stranger = make_user('Is Stranger', 'BEL-IA04');
        $projectId = make_project('BEL-PRJ-IA1', 'Is Assignee Project', $mgr);
        $taskId = $repo->create(['project_id' => $projectId, 'title' => 'T', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$empA, $empB]]);

        assert_true($repo->isAssignee($taskId, $empA));
        assert_true($repo->isAssignee($taskId, $empB));
        assert_false($repo->isAssignee($taskId, $stranger));
    });

    test('listOpenForAssignee finds a task for a secondary (non-primary) assignee', function () use ($repo) {
        $mgr = make_user('Open Manager', 'BEL-OA01', 'manager');
        $primary = make_user('Open Primary', 'BEL-OA02');
        $secondary = make_user('Open Secondary', 'BEL-OA03');
        $projectId = make_project('BEL-PRJ-OA1', 'Open Assignee Project', $mgr);
        $repo->create(['project_id' => $projectId, 'title' => 'Open task', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$primary, $secondary]]);

        $open = $repo->listOpenForAssignee($secondary);
        assert_true(in_array('Open task', array_column($open, 'title'), true), 'Secondary assignee should see the task in their open list');
    });

    test('listOpenForAssignee excludes done tasks', function () use ($repo) {
        $mgr = make_user('Done Manager', 'BEL-DA01', 'manager');
        $emp = make_user('Done Assignee', 'BEL-DA02');
        $projectId = make_project('BEL-PRJ-DA1', 'Done Assignee Project', $mgr);
        $taskId = $repo->create(['project_id' => $projectId, 'title' => 'Done task', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$emp]]);
        $repo->updateStatus($taskId, 'done');

        $open = $repo->listOpenForAssignee($emp);
        assert_false(in_array('Done task', array_column($open, 'title'), true));
    });

    test('assigneesForTasks batches across multiple tasks in one call', function () use ($repo) {
        $mgr = make_user('Batch Manager', 'BEL-BA01', 'manager');
        $empA = make_user('Batch Assignee A', 'BEL-BA02');
        $empB = make_user('Batch Assignee B', 'BEL-BA03');
        $projectId = make_project('BEL-PRJ-BA1', 'Batch Assignees Project', $mgr);
        $task1 = $repo->create(['project_id' => $projectId, 'title' => 'T1', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$empA]]);
        $task2 = $repo->create(['project_id' => $projectId, 'title' => 'T2', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr, 'assignees' => [$empB]]);

        $byTask = $repo->assigneesForTasks([$task1, $task2]);
        assert_equals($empA, $byTask[$task1][0]['id']);
        assert_equals($empB, $byTask[$task2][0]['id']);
    });

    test('bulkDelete removes exactly the given tasks and leaves others untouched', function () use ($repo) {
        $mgr = make_user('BulkDel Manager', 'BEL-BD01', 'manager');
        $projectId = make_project('BEL-PRJ-BD1', 'Bulk Delete Project', $mgr);
        $task1 = $repo->create(['project_id' => $projectId, 'title' => 'Delete me 1', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);
        $task2 = $repo->create(['project_id' => $projectId, 'title' => 'Delete me 2', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);
        $keep = $repo->create(['project_id' => $projectId, 'title' => 'Keep me', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);

        $repo->bulkDelete([$task1, $task2], $projectId);

        assert_null($repo->find($task1, $projectId));
        assert_null($repo->find($task2, $projectId));
        assert_not_null($repo->find($keep, $projectId));
    });

    test('bulkDelete only deletes tasks belonging to the given project', function () use ($repo) {
        $mgr = make_user('BulkDelScope Manager', 'BEL-BS01', 'manager');
        $projectA = make_project('BEL-PRJ-BS1', 'Bulk Delete Scope A', $mgr);
        $projectB = make_project('BEL-PRJ-BS2', 'Bulk Delete Scope B', $mgr);
        $taskInB = $repo->create(['project_id' => $projectB, 'title' => 'Other project task', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);

        $repo->bulkDelete([$taskInB], $projectA);

        assert_not_null($repo->find($taskInB, $projectB), 'A task from a different project must not be deleted');
    });

    test('bulkDelete with an empty array is a no-op', function () use ($repo) {
        $mgr = make_user('BulkDelNoop Manager', 'BEL-BN01', 'manager');
        $projectId = make_project('BEL-PRJ-BN1', 'Bulk Delete Noop Project', $mgr);
        $taskId = $repo->create(['project_id' => $projectId, 'title' => 'Untouched', 'description' => '', 'priority' => 'medium', 'created_by' => $mgr]);

        $repo->bulkDelete([], $projectId);

        assert_not_null($repo->find($taskId, $projectId));
    });
});
