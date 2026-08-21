<?php

use App\Repositories\DefectRepository;

test_suite('DefectRepository', function () {
    $repo = new DefectRepository();

    test('create() inserts a defect and find() returns it scoped to the project', function () use ($repo) {
        $mgr = make_user('Defect Manager', 'BEL-DF01', 'manager');
        $projectId = make_project('BEL-PRJ-DF1', 'Defect Test Project', $mgr);

        $defectId = $repo->create([
            'project_id' => $projectId,
            'code' => 'DEF-TEST01',
            'title' => 'Sonar signal drops during integration testing',
            'description' => 'Intermittent signal loss.',
            'severity' => 'critical',
            'reported_by' => $mgr,
        ]);

        $defect = $repo->find($defectId, $projectId);
        assert_not_null($defect);
        assert_equals('DEF-TEST01', $defect['code']);
        assert_equals('Sonar signal drops during integration testing', $defect['title']);
        assert_equals('critical', $defect['severity']);
        assert_equals('open', $defect['status']);
    });

    test('find() returns null for a defect belonging to a different project', function () use ($repo) {
        $mgr = make_user('Defect Manager 2', 'BEL-DF02', 'manager');
        $projectA = make_project('BEL-PRJ-DF2', 'Defect Project A', $mgr);
        $projectB = make_project('BEL-PRJ-DF3', 'Defect Project B', $mgr);

        $defectId = $repo->create([
            'project_id' => $projectA,
            'code' => 'DEF-TEST02',
            'title' => 'Scoped defect',
            'severity' => 'minor',
            'reported_by' => $mgr,
        ]);

        assert_null($repo->find($defectId, $projectB));
    });

    test('listForProject() includes the assignee and reporter names', function () use ($repo) {
        $mgr = make_user('Defect Manager 3', 'BEL-DF04', 'manager');
        $assignee = make_user('Defect Assignee', 'BEL-DF05');
        $projectId = make_project('BEL-PRJ-DF4', 'Defect List Project', $mgr);

        $repo->create([
            'project_id' => $projectId,
            'code' => 'DEF-TEST03',
            'title' => 'Incorrect target distance displayed',
            'severity' => 'major',
            'assigned_to' => $assignee,
            'reported_by' => $mgr,
        ]);

        $defects = $repo->listForProject($projectId);
        assert_count(1, $defects);
        assert_equals('DEF-TEST03', $defects[0]['code']);
        assert_equals('Defect Assignee', $defects[0]['assignee_name']);
        assert_equals('Defect Manager 3', $defects[0]['reporter_name']);
    });

    test('updateStatus() changes only the status field', function () use ($repo) {
        $mgr = make_user('Defect Manager 4', 'BEL-DF06', 'manager');
        $projectId = make_project('BEL-PRJ-DF5', 'Defect Status Project', $mgr);

        $defectId = $repo->create([
            'project_id' => $projectId,
            'code' => 'DEF-TEST04',
            'title' => 'UI freezes when loading large dataset',
            'severity' => 'minor',
            'reported_by' => $mgr,
        ]);

        $repo->updateStatus($defectId, 'resolved');
        $defect = $repo->find($defectId, $projectId);
        assert_equals('resolved', $defect['status']);
        assert_equals('UI freezes when loading large dataset', $defect['title']);
    });

    test('delete() removes the defect, scoped to its project', function () use ($repo) {
        $mgr = make_user('Defect Manager 5', 'BEL-DF07', 'manager');
        $projectId = make_project('BEL-PRJ-DF6', 'Defect Delete Project', $mgr);

        $defectId = $repo->create([
            'project_id' => $projectId,
            'code' => 'DEF-TEST05',
            'title' => 'To be deleted',
            'severity' => 'minor',
            'reported_by' => $mgr,
        ]);

        $repo->delete($defectId, $projectId);
        assert_null($repo->find($defectId, $projectId));
    });

    test('nextSuggestedCode() returns a DEF-### code', function () use ($repo) {
        $code = $repo->nextSuggestedCode();
        assert_true((bool)preg_match('/^DEF-\d{3}$/', $code), "Got: $code");
    });
});
