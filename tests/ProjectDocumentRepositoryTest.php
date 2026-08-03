<?php

use App\Repositories\ProjectDocumentRepository;

test_suite('ProjectDocumentRepository', function () {
    $repo = new ProjectDocumentRepository();

    test('create + forProject returns the document with uploader name', function () use ($repo) {
        $userId = make_user('Uploader One', 'BEL-UPL1');
        $managerId = make_user('Manager One', 'BEL-MGR1', 'manager');
        $projectId = make_project('BEL-PRJ-DOC1', 'Doc Test Project', $managerId);

        $repo->create($projectId, $userId, 'spec.pdf', 'abc123.pdf', 2048);

        $docs = $repo->forProject($projectId);
        assert_count(1, $docs);
        assert_equals('spec.pdf', $docs[0]['original_filename']);
        assert_equals('Uploader One', $docs[0]['uploader_name']);
    });

    test('forProject only returns documents for that project', function () use ($repo) {
        $userId = make_user('Uploader Two', 'BEL-UPL2');
        $managerId = make_user('Manager Two', 'BEL-MGR2', 'manager');
        $projectA = make_project('BEL-PRJ-DOCA', 'Project A', $managerId);
        $projectB = make_project('BEL-PRJ-DOCB', 'Project B', $managerId);

        $repo->create($projectA, $userId, 'a.pdf', 'stored-a.pdf', 100);
        $repo->create($projectB, $userId, 'b.pdf', 'stored-b.pdf', 100);

        assert_count(1, $repo->forProject($projectA));
        assert_count(1, $repo->forProject($projectB));
    });

    test('countForProject matches the number of documents', function () use ($repo) {
        $userId = make_user('Uploader Three', 'BEL-UPL3');
        $managerId = make_user('Manager Three', 'BEL-MGR3', 'manager');
        $projectId = make_project('BEL-PRJ-DOCC', 'Project C', $managerId);

        $repo->create($projectId, $userId, 'one.pdf', 's1.pdf', 100);
        $repo->create($projectId, $userId, 'two.pdf', 's2.pdf', 100);

        assert_equals(2, $repo->countForProject($projectId));
    });

    test('delete removes the document', function () use ($repo) {
        $userId = make_user('Uploader Four', 'BEL-UPL4');
        $managerId = make_user('Manager Four', 'BEL-MGR4', 'manager');
        $projectId = make_project('BEL-PRJ-DOCD', 'Project D', $managerId);
        $docId = $repo->create($projectId, $userId, 'gone.pdf', 'stored-gone.pdf', 100);

        $repo->delete($docId);

        assert_null($repo->find($docId));
        assert_count(0, $repo->forProject($projectId));
    });
});
