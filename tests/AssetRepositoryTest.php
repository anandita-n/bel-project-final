<?php

use App\Repositories\AssetRepository;

test_suite('AssetRepository', function () {
    $repo = new AssetRepository();

    test('codeExists is true only after the code is created', function () use ($repo) {
        assert_false($repo->codeExists('BEL-AST-TESTX'));
        $repo->create(['asset_code' => 'BEL-AST-TESTX', 'name' => 'Test Laptop', 'category' => 'laptop']);
        assert_true($repo->codeExists('BEL-AST-TESTX'));
    });

    test('create + findById round-trip preserves fields', function () use ($repo) {
        $id = $repo->create([
            'asset_code' => 'BEL-AST-TESTY', 'name' => 'Test Monitor', 'category' => 'monitor',
            'serial_number' => 'SN123', 'department' => 'Engineering',
        ]);
        $found = $repo->findById($id);
        assert_equals('Test Monitor', $found['name']);
        assert_equals('monitor', $found['category']);
        assert_equals('SN123', $found['serial_number']);
        assert_equals('available', $found['status'], 'New assets default to available');
        assert_null($found['assigned_to']);
    });

    test('update changes name/category/department without touching status or assignment', function () use ($repo) {
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTU', 'name' => 'Old Name', 'category' => 'mouse']);
        $repo->update($id, ['name' => 'New Name', 'category' => 'keyboard', 'serial_number' => '', 'department' => '', 'purchase_date' => '', 'warranty_expiry' => '']);
        $found = $repo->findById($id);
        assert_equals('New Name', $found['name']);
        assert_equals('keyboard', $found['category']);
        assert_equals('available', $found['status']);
    });

    test('assign sets assigned_to and flips status to assigned', function () use ($repo) {
        $userId = make_user('Assignee One', 'BEL-ASGN1');
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTA', 'name' => 'Test Tablet', 'category' => 'mobile_device']);
        $repo->assign($id, $userId);
        $found = $repo->findById($id);
        assert_equals($userId, (int)$found['assigned_to']);
        assert_equals('assigned', $found['status']);
    });

    test('unassigning (null) reverts an assigned asset back to available', function () use ($repo) {
        $userId = make_user('Assignee Two', 'BEL-ASGN2');
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTB', 'name' => 'Test Board', 'category' => 'development_board']);
        $repo->assign($id, $userId);
        $repo->assign($id, null);
        $found = $repo->findById($id);
        assert_null($found['assigned_to']);
        assert_equals('available', $found['status']);
    });

    test('unassigning an asset that is under_repair leaves its status alone', function () use ($repo) {
        $userId = make_user('Assignee Three', 'BEL-ASGN3');
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTC', 'name' => 'Test Scope', 'category' => 'testing_equipment']);
        $repo->assign($id, $userId);
        $repo->setStatus($id, 'under_repair');
        $repo->assign($id, null);
        $found = $repo->findById($id);
        assert_null($found['assigned_to']);
        assert_equals('under_repair', $found['status'], 'Un-assigning should not silently mark it available again');
    });

    test('setStatus to retired clears any existing assignment', function () use ($repo) {
        $userId = make_user('Assignee Four', 'BEL-ASGN4');
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTD', 'name' => 'Test Switch', 'category' => 'network_equipment']);
        $repo->assign($id, $userId);
        $repo->setStatus($id, 'retired');
        $found = $repo->findById($id);
        assert_null($found['assigned_to']);
        assert_equals('retired', $found['status']);
    });

    test('setStatus to lost clears any existing assignment', function () use ($repo) {
        $userId = make_user('Assignee Five', 'BEL-ASGN5');
        $id = $repo->create(['asset_code' => 'BEL-AST-TESTE', 'name' => 'Test License', 'category' => 'software_license']);
        $repo->assign($id, $userId);
        $repo->setStatus($id, 'lost');
        $found = $repo->findById($id);
        assert_null($found['assigned_to']);
        assert_equals('lost', $found['status']);
    });

    test('search scoped to an employeeId only returns that employee\'s assets', function () use ($repo) {
        $userA = make_user('Search Employee A', 'BEL-SRCHA');
        $userB = make_user('Search Employee B', 'BEL-SRCHB');
        $idA = $repo->create(['asset_code' => 'BEL-AST-SRCHA', 'name' => 'Owned by A', 'category' => 'laptop']);
        $idB = $repo->create(['asset_code' => 'BEL-AST-SRCHB', 'name' => 'Owned by B', 'category' => 'laptop']);
        $repo->assign($idA, $userA);
        $repo->assign($idB, $userB);

        $results = $repo->search('', '', '', $userA);
        $codes = array_column($results, 'asset_code');
        assert_contains('BEL-AST-SRCHA', $codes);
        assert_true(!in_array('BEL-AST-SRCHB', $codes, true), 'Should not see another employee\'s asset');
    });

    test('search filters by category and status together', function () use ($repo) {
        $repo->create(['asset_code' => 'BEL-AST-FILT1', 'name' => 'Filter Laptop', 'category' => 'laptop']);
        $id2 = $repo->create(['asset_code' => 'BEL-AST-FILT2', 'name' => 'Filter Mouse', 'category' => 'mouse']);
        $repo->setStatus($id2, 'lost');

        $results = $repo->search('', 'mouse', 'lost', null);
        assert_count(1, $results);
        assert_equals('BEL-AST-FILT2', $results[0]['asset_code']);
    });
});
