<?php

// Pure functions from includes/helpers.php — no DB interaction needed, but each still runs
// inside test()'s transaction wrapper for consistency with the rest of the suite.
require_once __DIR__ . '/../includes/helpers.php';

test_suite('helpers.php (pure functions)', function () {
    test('initials uses first+last initial for a two-word name', function () {
        assert_equals('JD', initials('John Doe'));
    });

    test('initials uses first two letters for a single-word name', function () {
        assert_equals('JO', initials('John'));
    });

    test('initials falls back to ? for an empty name', function () {
        assert_equals('?', initials('   '));
    });

    test('initials uses first+last for names with a middle name', function () {
        assert_equals('JS', initials('John Quincy Smith'));
    });

    test('avatar_class maps admin', function () {
        assert_equals('avatar-admin', avatar_class('admin'));
    });

    test('avatar_class maps manager', function () {
        assert_equals('avatar-manager', avatar_class('manager'));
    });

    test('avatar_class defaults unknown roles to employee', function () {
        assert_equals('avatar-employee', avatar_class('employee'));
        assert_equals('avatar-employee', avatar_class('something-else'));
    });

    test('asset_status_tag_class maps every AssetRepository status', function () {
        assert_equals('dir-badge-available', asset_status_tag_class('available'));
        assert_equals('dir-badge-assigned', asset_status_tag_class('assigned'));
        assert_equals('dir-badge-under_repair', asset_status_tag_class('under_repair'));
        assert_equals('dir-badge-retired', asset_status_tag_class('retired'));
        assert_equals('dir-badge-lost', asset_status_tag_class('lost'));
    });

    test('asset_status_tag_class falls back to dir-badge-retired for an unknown status', function () {
        assert_equals('dir-badge-retired', asset_status_tag_class('made_up_status'));
    });

    test('like_escape escapes SQL LIKE wildcards', function () {
        assert_equals('50\\% off', like_escape('50% off'));
        assert_equals('a\\_b', like_escape('a_b'));
    });

    test('is_valid_password accepts compliant passwords', function () {
        assert_true(is_valid_password('Bel@2026'));
        assert_true(is_valid_password('Radar#123'));
        assert_true(is_valid_password('Employee!45'));
    });

    test('is_valid_password rejects passwords missing a required rule', function () {
        assert_false(is_valid_password('password'));
        assert_false(is_valid_password('BEL12345'));
        assert_false(is_valid_password('abcdefgh'));
        assert_false(is_valid_password('12345678'));
        assert_false(is_valid_password('Bel 2026'));
    });

    test('is_valid_password enforces the 32-character maximum', function () {
        assert_false(is_valid_password(str_repeat('Aa1!', 9)));
    });

    test('is_valid_email accepts well-formed addresses', function () {
        assert_true(is_valid_email('rahul.sharma@bel.co.in'));
        assert_true(is_valid_email('anandita@gmail.com'));
        assert_true(is_valid_email('employee123@yahoo.com'));
    });

    test('is_valid_email rejects malformed addresses', function () {
        assert_false(is_valid_email('rahulgmail.com'));
        assert_false(is_valid_email('rahul@'));
        assert_false(is_valid_email('@gmail.com'));
        assert_false(is_valid_email('rahul @gmail.com'));
        assert_false(is_valid_email('rahul@gmail'));
    });
});
