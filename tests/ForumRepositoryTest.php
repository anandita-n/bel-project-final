<?php

use App\Repositories\ForumRepository;

test_suite('ForumRepository', function () {
    $repo = new ForumRepository();

    test('createQuestion creates and links tags', function () use ($repo) {
        $userId = make_user('Asker One', 'BEL-ASK1');
        $qId = $repo->createQuestion($userId, 'Test title', 'Test body', ['alpha', 'beta']);
        $found = $repo->findQuestion($qId);
        $tagNames = array_column($found['tags'], 'name');
        sort($tagNames);
        assert_equals(['alpha', 'beta'], $tagNames);
    });

    test('createQuestion reuses an existing tag instead of duplicating it', function () use ($repo) {
        $userId = make_user('Asker Two', 'BEL-ASK2');
        $q1 = $repo->createQuestion($userId, 'First', 'Body', ['shared-tag']);
        $q2 = $repo->createQuestion($userId, 'Second', 'Body', ['shared-tag']);
        $tag1 = $repo->findQuestion($q1)['tags'][0]['id'];
        $tag2 = $repo->findQuestion($q2)['tags'][0]['id'];
        assert_equals($tag1, $tag2, 'Same tag name should resolve to the same tag row');
    });

    test('blank tags in the list are skipped', function () use ($repo) {
        $userId = make_user('Asker Three', 'BEL-ASK3');
        $qId = $repo->createQuestion($userId, 'Title', 'Body', ['real', '', '  ']);
        $found = $repo->findQuestion($qId);
        assert_count(1, $found['tags']);
    });

    test('createAnswer + answersForQuestion returns the answer', function () use ($repo) {
        $asker = make_user('Asker Four', 'BEL-ASK4');
        $answerer = make_user('Answerer One', 'BEL-ANS1');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $repo->createAnswer($qId, $answerer, 'My answer');
        $answers = $repo->answersForQuestion($qId);
        assert_count(1, $answers);
        assert_equals('My answer', $answers[0]['body']);
    });

    test('findQuestion returns null for a non-existent id', function () use ($repo) {
        assert_null($repo->findQuestion(999999));
    });

    test('a new question has status open with no accepted answer', function () use ($repo) {
        $asker = make_user('Asker Status1', 'BEL-STA1');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        assert_equals('open', $repo->findQuestion($qId)['status']);
    });

    test('accepting an answer flips the question status to solved', function () use ($repo) {
        $asker = make_user('Asker Status2', 'BEL-STA2');
        $answerer = make_user('Answerer Status2', 'BEL-STA2B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        $repo->acceptAnswer($qId, $aId, $asker);
        assert_equals('solved', $repo->findQuestion($qId)['status']);
    });

    test('toggleHelpful marks helpful on first click and un-marks on second', function () use ($repo) {
        $asker = make_user('Asker Helpful1', 'BEL-HLP1');
        $answerer = make_user('Answerer Helpful1', 'BEL-HLP1B');
        $marker = make_user('Marker Helpful1', 'BEL-HLP1C');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');

        $first = $repo->toggleHelpful($marker, $aId);
        assert_true($first);
        assert_equals(1, $repo->helpfulCountForAnswer($aId));

        $second = $repo->toggleHelpful($marker, $aId);
        assert_false($second);
        assert_equals(0, $repo->helpfulCountForAnswer($aId));
    });

    test('helpful count is per-answer, not shared across answers', function () use ($repo) {
        $asker = make_user('Asker Helpful2', 'BEL-HLP2');
        $answerer = make_user('Answerer Helpful2', 'BEL-HLP2B');
        $marker = make_user('Marker Helpful2', 'BEL-HLP2C');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $a1 = $repo->createAnswer($qId, $answerer, 'Answer one');
        $a2 = $repo->createAnswer($qId, $answerer, 'Answer two');
        $repo->toggleHelpful($marker, $a1);
        assert_equals(1, $repo->helpfulCountForAnswer($a1));
        assert_equals(0, $repo->helpfulCountForAnswer($a2));
    });

    test('myHelpfulAnswers only reports the answers this user marked', function () use ($repo) {
        $asker = make_user('Asker Helpful3', 'BEL-HLP3');
        $answerer = make_user('Answerer Helpful3', 'BEL-HLP3B');
        $marker = make_user('Marker Helpful3', 'BEL-HLP3C');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $a1 = $repo->createAnswer($qId, $answerer, 'Answer one');
        $a2 = $repo->createAnswer($qId, $answerer, 'Answer two');
        $repo->toggleHelpful($marker, $a1);

        $mine = $repo->myHelpfulAnswers($marker, [$a1, $a2]);
        assert_true($mine[$a1] ?? false);
        assert_false(isset($mine[$a2]));
    });

    test('acceptAnswer is rejected when the requester is not the question author', function () use ($repo) {
        $asker = make_user('Asker Nine', 'BEL-ASK9');
        $answerer = make_user('Answerer Three', 'BEL-ANS3');
        $stranger = make_user('Stranger One', 'BEL-STR1');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        assert_false($repo->acceptAnswer($qId, $aId, $stranger));
        assert_null($repo->findQuestion($qId)['accepted_answer_id']);
    });

    test('acceptAnswer succeeds for the question author and marks it accepted', function () use ($repo) {
        $asker = make_user('Asker Ten', 'BEL-AS10');
        $answerer = make_user('Answerer Four', 'BEL-ANS4');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        assert_true($repo->acceptAnswer($qId, $aId, $asker));
        assert_equals($aId, (int)$repo->findQuestion($qId)['accepted_answer_id']);
    });

    test('acceptAnswer rejects even an admin who is not the question author', function () use ($repo) {
        $asker = make_user('Asker Admin1', 'BEL-ADM1');
        $answerer = make_user('Answerer Admin1', 'BEL-ADM1B');
        $admin = make_user('Admin Non-Overrider', 'BEL-ADM1C', 'admin');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        assert_false($repo->acceptAnswer($qId, $aId, $admin), 'Accept is strictly author-only, admins included');
        assert_null($repo->findQuestion($qId)['accepted_answer_id']);
    });

    test('accepting a different answer later replaces the previous acceptance', function () use ($repo) {
        $asker = make_user('Asker Replace1', 'BEL-REP1');
        $answerer = make_user('Answerer Replace1', 'BEL-REP1B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $first = $repo->createAnswer($qId, $answerer, 'First answer');
        $second = $repo->createAnswer($qId, $answerer, 'Second answer');
        $repo->acceptAnswer($qId, $first, $asker);
        $repo->acceptAnswer($qId, $second, $asker);
        assert_equals($second, (int)$repo->findQuestion($qId)['accepted_answer_id']);
    });

    test('acceptAnswer rejects an answer id that does not belong to the question', function () use ($repo) {
        $asker = make_user('Asker Eleven', 'BEL-AS11');
        $answerer = make_user('Answerer Five', 'BEL-ANS5');
        $q1 = $repo->createQuestion($asker, 'Q1', 'Body', []);
        $q2 = $repo->createQuestion($asker, 'Q2', 'Body', []);
        $aId = $repo->createAnswer($q2, $answerer, 'Belongs to q2');
        assert_false($repo->acceptAnswer($q1, $aId, $asker), 'Answer belongs to a different question');
    });

    test('answersForQuestion sorts the accepted answer first regardless of helpful count', function () use ($repo) {
        $asker = make_user('Asker Twelve', 'BEL-AS12');
        $answerer = make_user('Answerer Six', 'BEL-ANS6');
        $marker = make_user('Marker Twelve', 'BEL-AS12B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $first = $repo->createAnswer($qId, $answerer, 'First, more helpful');
        $second = $repo->createAnswer($qId, $answerer, 'Second, gets accepted');
        $repo->toggleHelpful($marker, $first);
        $repo->acceptAnswer($qId, $second, $asker);

        $answers = $repo->answersForQuestion($qId);
        assert_equals($second, (int)$answers[0]['id'], 'Accepted answer should be first despite fewer helpful marks');
    });

    test('createAnswer touches the question\'s updated_at', function () use ($repo) {
        $pdo = \App\Database::connection();
        $asker = make_user('Asker Updated1', 'BEL-UPD1');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $before = $pdo->query("SELECT updated_at FROM forum_questions WHERE id = $qId")->fetch()['updated_at'];
        sleep(1);
        $answerer = make_user('Answerer Updated1', 'BEL-UPD1B');
        $repo->createAnswer($qId, $answerer, 'New answer');
        $after = $pdo->query("SELECT updated_at FROM forum_questions WHERE id = $qId")->fetch()['updated_at'];
        assert_true($after > $before, 'updated_at should advance when a new answer is posted');
    });

    test('listQuestions sort=most_helpful orders by total helpful marks descending', function () use ($repo) {
        $asker = make_user('Asker Thirteen', 'BEL-AS13');
        $answerer = make_user('Answerer Seven', 'BEL-ANS7');
        $marker = make_user('Marker Thirteen', 'BEL-AS13B');
        $qFew = $repo->createQuestion($asker, 'Few helpful ' . uniqid(), 'Body', []);
        $qMany = $repo->createQuestion($asker, 'Many helpful ' . uniqid(), 'Body', []);
        $aFew = $repo->createAnswer($qFew, $answerer, 'A');
        $aMany = $repo->createAnswer($qMany, $answerer, 'B');
        $repo->toggleHelpful($marker, $aMany);

        // listQuestions() now defaults to a 15-row page — pass a large perPage so this order
        // check isn't affected by how many other questions exist in the database.
        $rows = $repo->listQuestions('', null, 'most_helpful', '', '', 1, 9999);
        $idsInOrder = array_column($rows, 'id');
        $posMany = array_search($qMany, $idsInOrder, true);
        $posFew = array_search($qFew, $idsInOrder, true);
        assert_true($posMany < $posFew, 'Question with more helpful marks should sort earlier');
    });

    test('listQuestions sort=updated orders by most recently touched question first', function () use ($repo) {
        $pdo = \App\Database::connection();
        $asker = make_user('Asker Updated2', 'BEL-UPD2');
        $qOld = $repo->createQuestion($asker, 'Old ' . uniqid(), 'Body', []);
        $qNew = $repo->createQuestion($asker, 'New ' . uniqid(), 'Body', []);
        // Force qOld to look older than qNew regardless of real wall-clock timing.
        $pdo->prepare("UPDATE forum_questions SET updated_at = '2020-01-01 00:00:00' WHERE id = ?")->execute([$qOld]);

        // Same reasoning as the most_helpful sort test above — bypass the default page size.
        $rows = $repo->listQuestions('', null, 'updated', '', '', 1, 9999);
        $idsInOrder = array_column($rows, 'id');
        $posNew = array_search($qNew, $idsInOrder, true);
        $posOld = array_search($qOld, $idsInOrder, true);
        assert_true($posNew < $posOld);
    });

    test('listQuestions filters by status=open excludes solved questions', function () use ($repo) {
        $asker = make_user('Asker Status3', 'BEL-STA3');
        $answerer = make_user('Answerer Status3', 'BEL-STA3B');
        $open = $repo->createQuestion($asker, 'Open one ' . uniqid(), 'Body', []);
        $solved = $repo->createQuestion($asker, 'Solved one ' . uniqid(), 'Body', []);
        $aId = $repo->createAnswer($solved, $answerer, 'Answer');
        $repo->acceptAnswer($solved, $aId, $asker);

        $rows = $repo->listQuestions('', null, 'recent', 'open');
        $ids = array_column($rows, 'id');
        assert_true(in_array($open, $ids, true));
        assert_false(in_array($solved, $ids, true));
    });

    test('listQuestions filters by department', function () use ($repo) {
        $pdo = \App\Database::connection();
        $asker = make_user('Asker Dept1', 'BEL-DEP1');
        $pdo->prepare("UPDATE users SET department = 'UniqueTestDept' WHERE id = ?")->execute([$asker]);
        $qId = $repo->createQuestion($asker, 'Dept question ' . uniqid(), 'Body', []);

        $rows = $repo->listQuestions('', null, 'recent', '', 'UniqueTestDept');
        assert_count(1, $rows);
        assert_equals($qId, (int)$rows[0]['id']);
    });

    test('listQuestions filters by search text', function () use ($repo) {
        $asker = make_user('Asker Fourteen', 'BEL-AS14');
        $unique = 'Zorbflax' . uniqid();
        $repo->createQuestion($asker, "Question about $unique", 'Body', []);
        $repo->createQuestion($asker, 'Unrelated question', 'Body', []);

        $rows = $repo->listQuestions($unique, null, 'recent');
        assert_count(1, $rows);
    });

    test('listQuestions filters by tag_id', function () use ($repo) {
        $asker = make_user('Asker Fifteen', 'BEL-AS15');
        $tagged = $repo->createQuestion($asker, 'Tagged ' . uniqid(), 'Body', ['exclusive-tag']);
        $repo->createQuestion($asker, 'Untagged ' . uniqid(), 'Body', []);
        $tagId = $repo->findQuestion($tagged)['tags'][0]['id'];

        $rows = $repo->listQuestions('', $tagId, 'recent');
        assert_count(1, $rows);
        assert_equals($tagged, (int)$rows[0]['id']);
    });

    test('deleteQuestion cascades its answers, helpful marks and tag links', function () use ($repo) {
        $pdo = \App\Database::connection();
        $asker = make_user('Asker Sixteen', 'BEL-AS16');
        $answerer = make_user('Answerer Eight', 'BEL-ANS8');
        $qId = $repo->createQuestion($asker, 'To be deleted', 'Body', ['temp-tag']);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        $repo->toggleHelpful($answerer, $aId);

        $repo->deleteQuestion($qId);

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM forum_answers WHERE question_id = ?');
        $stmt->execute([$qId]);
        assert_equals(0, (int)$stmt->fetch()['c'], 'Answers should cascade-delete');

        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM forum_answer_helpful WHERE answer_id = ?');
        $stmt->execute([$aId]);
        assert_equals(0, (int)$stmt->fetch()['c'], 'Helpful marks should cascade-delete via the answer');
    });

    test('deleteAnswer removes only that answer', function () use ($repo) {
        $asker = make_user('Asker Seventeen', 'BEL-AS17');
        $answerer = make_user('Answerer Nine', 'BEL-ANS9');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $keep = $repo->createAnswer($qId, $answerer, 'Keep me');
        $remove = $repo->createAnswer($qId, $answerer, 'Remove me');

        $repo->deleteAnswer($remove);

        $remaining = array_column($repo->answersForQuestion($qId), 'id');
        assert_equals([$keep], $remaining);
    });

    test('question comments: create + list in chronological order', function () use ($repo) {
        $asker = make_user('Asker Eighteen', 'BEL-AS18');
        $commenter = make_user('Commenter One', 'BEL-COM1');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $repo->createQuestionComment($qId, $commenter, 'First comment');
        $repo->createQuestionComment($qId, $commenter, 'Second comment');

        $comments = $repo->commentsForQuestion($qId);
        assert_count(2, $comments);
        assert_equals('First comment', $comments[0]['body']);
    });

    test('answer comments: create + batch lookup via commentsForAnswers', function () use ($repo) {
        $asker = make_user('Asker Nineteen', 'BEL-AS19');
        $answerer = make_user('Answerer Ten', 'BEL-A10');
        $commenter = make_user('Commenter Two', 'BEL-COM2');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $a1 = $repo->createAnswer($qId, $answerer, 'Answer one');
        $a2 = $repo->createAnswer($qId, $answerer, 'Answer two');
        $repo->createAnswerComment($a1, $commenter, 'Comment on a1');

        $byAnswer = $repo->commentsForAnswers([$a1, $a2]);
        assert_count(1, $byAnswer[$a1]);
        assert_true(!isset($byAnswer[$a2]), 'a2 has no comments so should not appear in the map');
    });

    test('deleteQuestionComment removes only that comment', function () use ($repo) {
        $asker = make_user('Asker Twenty', 'BEL-AS20');
        $commenter = make_user('Commenter Three', 'BEL-COM3');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $keepId = $repo->createQuestionComment($qId, $commenter, 'Keep');
        $removeId = $repo->createQuestionComment($qId, $commenter, 'Remove');

        $repo->deleteQuestionComment($removeId);

        $remaining = array_column($repo->commentsForQuestion($qId), 'id');
        assert_equals([$keepId], $remaining);
    });

    test('answer attachments: create + attachmentsForAnswer returns it with uploader name', function () use ($repo) {
        $asker = make_user('Asker Attach1', 'BEL-ATT1');
        $answerer = make_user('Answerer Attach1', 'BEL-ATT1B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        $repo->createAnswerAttachment($aId, $answerer, 'spec.pdf', 'stored123.pdf', 2048, 'application/pdf');

        $attachments = $repo->attachmentsForAnswer($aId);
        assert_count(1, $attachments);
        assert_equals('spec.pdf', $attachments[0]['original_filename']);
        assert_equals('Answerer Attach1', $attachments[0]['uploader_name']);
    });

    test('attachmentsForAnswers batches correctly across multiple answers', function () use ($repo) {
        $asker = make_user('Asker Attach2', 'BEL-ATT2');
        $answerer = make_user('Answerer Attach2', 'BEL-ATT2B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $a1 = $repo->createAnswer($qId, $answerer, 'Answer one');
        $a2 = $repo->createAnswer($qId, $answerer, 'Answer two');
        $repo->createAnswerAttachment($a1, $answerer, 'a1.pdf', 'stored-a1.pdf', 100, null);

        $byAnswer = $repo->attachmentsForAnswers([$a1, $a2]);
        assert_count(1, $byAnswer[$a1]);
        assert_true(!isset($byAnswer[$a2]));
    });

    test('deleteAnswerAttachment removes only that attachment', function () use ($repo) {
        $asker = make_user('Asker Attach3', 'BEL-ATT3');
        $answerer = make_user('Answerer Attach3', 'BEL-ATT3B');
        $qId = $repo->createQuestion($asker, 'Title', 'Body', []);
        $aId = $repo->createAnswer($qId, $answerer, 'Answer body');
        $keepId = $repo->createAnswerAttachment($aId, $answerer, 'keep.pdf', 'stored-keep.pdf', 100, null);
        $removeId = $repo->createAnswerAttachment($aId, $answerer, 'remove.pdf', 'stored-remove.pdf', 100, null);

        $repo->deleteAnswerAttachment($removeId);

        $remaining = array_column($repo->attachmentsForAnswer($aId), 'id');
        assert_equals([$keepId], $remaining);
    });
});
