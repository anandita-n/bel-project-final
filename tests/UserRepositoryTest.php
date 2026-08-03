<?php

use App\Repositories\UserRepository;

test_suite('UserRepository (hierarchy search)', function () {
    $repo = new UserRepository();

    test('searchOneWithHierarchy finds an employee by exact employee code', function () use ($repo) {
        $mgr = make_user('Hierarchy Manager', 'BEL-HM01', 'manager');
        make_user('Hierarchy Employee', 'BEL-HE01', 'employee', $mgr);

        $result = $repo->searchOneWithHierarchy('BEL-HE01');
        assert_not_null($result);
        assert_equals('Hierarchy Employee', $result['employee']['name']);
    });

    test('searchOneWithHierarchy finds by partial name match', function () use ($repo) {
        make_user('Zephyr Uniquename', 'BEL-ZU01');
        $result = $repo->searchOneWithHierarchy('Zephyr');
        assert_not_null($result);
        assert_equals('Zephyr Uniquename', $result['employee']['name']);
    });

    test('searchOneWithHierarchy returns the manager when one exists', function () use ($repo) {
        $mgr = make_user('Manager With Report', 'BEL-MWR1', 'manager');
        make_user('Report Of Manager', 'BEL-ROM1', 'employee', $mgr);

        $result = $repo->searchOneWithHierarchy('BEL-ROM1');
        assert_not_null($result['manager']);
        assert_equals('Manager With Report', $result['manager']['name']);
    });

    test('searchOneWithHierarchy returns a null manager for a top-level employee', function () use ($repo) {
        make_user('Top Level Person', 'BEL-TLP1', 'manager', null);
        $result = $repo->searchOneWithHierarchy('BEL-TLP1');
        assert_null($result['manager']);
    });

    test('searchOneWithHierarchy returns direct reports', function () use ($repo) {
        $mgr = make_user('Boss Person', 'BEL-BOSS1', 'manager');
        make_user('Report One', 'BEL-REP1', 'employee', $mgr);
        make_user('Report Two', 'BEL-REP2', 'employee', $mgr);

        $result = $repo->searchOneWithHierarchy('BEL-BOSS1');
        assert_count(2, $result['direct_reports']);
    });

    test('searchOneWithHierarchy returns no direct reports for an individual contributor', function () use ($repo) {
        make_user('Solo Contributor', 'BEL-SOLO1');
        $result = $repo->searchOneWithHierarchy('BEL-SOLO1');
        assert_count(0, $result['direct_reports']);
    });

    test('searchOneWithHierarchy returns null when nothing matches', function () use ($repo) {
        assert_null($repo->searchOneWithHierarchy('NoSuchEmployeeXYZ123'));
    });

    test('searchOneWithHierarchy ignores inactive (soft-deleted) employees', function () use ($repo) {
        $id = make_user('Inactive Person', 'BEL-INAC1');
        \App\Database::connection()->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
        assert_null($repo->searchOneWithHierarchy('BEL-INAC1'));
    });

    test('nextEmployeeCode returns one past the highest existing BEL number', function () use ($repo) {
        \App\Database::connection()->exec("INSERT INTO users (employee_code, name, email, password, role, is_active) VALUES ('BEL9998', 'Code Probe', 'code.probe@bel.co.in', 'x', 'employee', 1)");
        assert_equals('BEL9999', $repo->nextEmployeeCode());
    });

    test('nextEmployeeCode is unique from an existing code', function () use ($repo) {
        $code = $repo->nextEmployeeCode();
        assert_false($repo->emailOrCodeExists('nobody@bel.co.in', $code));
    });

    test('updatePassword rehashes the password and sets must_change_password', function () use ($repo) {
        $id = make_user('Password Rotator', 'BEL-PW01');
        $repo->updatePassword($id, 'NewPass#123', true);
        $row = $repo->findById($id);
        assert_true(password_verify('NewPass#123', $row['password']));
        assert_equals(1, (int)$row['must_change_password']);
    });

    test('updatePassword can clear must_change_password', function () use ($repo) {
        $id = make_user('Password Clearer', 'BEL-PW02');
        $repo->updatePassword($id, 'AnotherPass#1', false);
        $row = $repo->findById($id);
        assert_equals(0, (int)$row['must_change_password']);
    });
});
