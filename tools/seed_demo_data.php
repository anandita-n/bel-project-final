<?php
/**
 * Fills a freshly-migrated BEL PMS database with realistic demo data for a demo/walkthrough:
 * a handful of departments, managers, employees, projects (with members), tasks, and defects.
 *
 * Uses the app's own repositories (not raw INSERT statements with hardcoded IDs), so it works
 * correctly on any fresh database regardless of auto-increment starting point.
 *
 * Usage (from the project root):
 *   php tools/seed_demo_data.php
 *   php tools/seed_demo_data.php --force   (re-run even if non-admin users already exist)
 *
 * Expects schema.sql and every sql/0*.sql migration to already be loaded (run setup-bel-pms.ps1
 * first, or run them by hand) — this only inserts rows into tables that must already exist.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\DefectRepository;

$force = in_array('--force', $argv, true);

$users = new UserRepository();
$projects = new ProjectRepository();
$tasks = new TaskRepository();
$defects = new DefectRepository();

// ---------------------------------------------------------------------------
// Guard: don't pile duplicate demo data onto a database that already has some.
// ---------------------------------------------------------------------------
$existingCount = (int)\App\Database::connection()
    ->query("SELECT COUNT(*) c FROM users WHERE employee_code != 'BEL0001'")
    ->fetch()['c'];

if ($existingCount > 0 && !$force) {
    fwrite(STDERR, "Found $existingCount existing non-admin user(s) already in the database.\n");
    fwrite(STDERR, "Re-running this would create duplicate demo data. Pass --force to do it anyway.\n");
    exit(1);
}

echo "Seeding demo data...\n";

// ---------------------------------------------------------------------------
// Departments: each gets a manager, a handful of employees, and 2 projects.
// ---------------------------------------------------------------------------
$departments = [
    'Engineering' => [
        'manager' => 'Rajesh Kumar',
        'employees' => ['Anjali Menon', 'Deepak Iyer', 'Neha Gupta', 'Lakshmi Bose', 'Divya Mishra'],
        'projects' => ['Radar Signal Processing Upgrade', 'Simulator Software Modernization'],
    ],
    'Design' => [
        'manager' => 'Anil Verma',
        'employees' => ['Kiran Chatterjee', 'Rahul Saxena', 'Swati Malhotra'],
        'projects' => ['Avionics Display Revamp', 'Battlefield Management System UI'],
    ],
    'Operations' => [
        'manager' => 'Sunita Reddy',
        'employees' => ['Ravi Rao', 'Priya Sharma', 'Vikram Nair'],
        'projects' => ['Production Line Automation', 'Supply Chain Digitization'],
    ],
    'Quality Assurance' => [
        'manager' => 'Amit Singh',
        'employees' => ['Meera Pillai', 'Arjun Reddy'],
        'projects' => ['Missile Guidance QA Certification'],
    ],
    'Human Resources' => [
        'manager' => 'Kavita Joshi',
        'employees' => ['Rohan Desai', 'Sneha Kulkarni'],
        'projects' => ['Employee Onboarding Portal'],
    ],
];

$taskTitles = [
    'Requirements analysis', 'Design review', 'Implementation', 'Unit testing',
    'Integration testing', 'Documentation', 'Code review', 'Performance tuning',
];
$statuses = ['todo', 'in_progress', 'review', 'done'];
$priorities = ['low', 'medium', 'high'];
$severities = array_keys(DefectRepository::SEVERITIES);
$defectStatuses = array_keys(DefectRepository::STATUSES);

function makeEmail(string $name): string {
    $slug = strtolower(str_replace(' ', '.', $name));
    return $slug . '@bel.co.in';
}

$createdUsers = []; // name => id
$defectSeq = 1;

foreach ($departments as $deptName => $dept) {
    echo "  Department: $deptName\n";

    // --- Manager ---
    $mgrCode = $users->nextEmployeeCode();
    $mgrId = $users->create([
        'employee_code' => $mgrCode,
        'name' => $dept['manager'],
        'email' => makeEmail($dept['manager']),
        'password' => 'Test1234!',
        'must_change_password' => true,
        'role' => 'manager',
        'department' => $deptName,
        'manager_id' => null,
        'stream' => '',
        'telephone' => '',
        'user_group' => '',
    ]);
    $createdUsers[$dept['manager']] = $mgrId;
    echo "    + manager {$dept['manager']} ($mgrCode)\n";

    // --- Employees, reporting to the department manager ---
    $deptEmployeeIds = [];
    foreach ($dept['employees'] as $empName) {
        $empCode = $users->nextEmployeeCode();
        $empId = $users->create([
            'employee_code' => $empCode,
            'name' => $empName,
            'email' => makeEmail($empName),
            'password' => 'Test1234!',
            'must_change_password' => true,
            'role' => 'employee',
            'department' => $deptName,
            'manager_id' => $mgrId,
            'stream' => '',
            'telephone' => '',
            'user_group' => '',
        ]);
        $createdUsers[$empName] = $empId;
        $deptEmployeeIds[] = $empId;
        echo "    + employee $empName ($empCode)\n";
    }

    // --- Projects, each with 2-4 of the department's employees as members and some tasks ---
    foreach ($dept['projects'] as $projectName) {
        $code = $projects->nextSuggestedCode();
        $projectId = $projects->create([
            'project_code' => $code,
            'name' => $projectName,
            'description' => "Departmental initiative under $deptName to deliver: $projectName.",
            'department' => $deptName,
            'priority' => $priorities[array_rand($priorities)],
            'manager_id' => $mgrId,
            'start_date' => date('Y-m-d', strtotime('-' . rand(10, 90) . ' days')),
            'due_date' => date('Y-m-d', strtotime('+' . rand(30, 120) . ' days')),
        ]);
        echo "    + project $projectName ($code)\n";

        $memberCount = min(count($deptEmployeeIds), rand(2, 4));
        $memberIds = (array)array_rand(array_flip($deptEmployeeIds), $memberCount);
        $roles = ['Developer', 'Tester', 'Analyst', 'Contributor', 'Reviewer'];
        foreach ($memberIds as $memberId) {
            $projects->addMember($projectId, (int)$memberId, $roles[array_rand($roles)], 'member');
        }

        $taskCount = rand(4, 6);
        for ($i = 0; $i < $taskCount; $i++) {
            $assignee = $memberIds ? (int)$memberIds[array_rand($memberIds)] : $mgrId;
            $status = $statuses[array_rand($statuses)];
            $taskId = $tasks->create([
                'project_id' => $projectId,
                'title' => $taskTitles[$i % count($taskTitles)] . ' - ' . $projectName,
                'description' => '',
                'assigned_to' => $assignee,
                'priority' => $priorities[array_rand($priorities)],
                'start_date' => null,
                'due_date' => date('Y-m-d', strtotime('+' . rand(5, 60) . ' days')),
                'created_by' => $mgrId,
            ]);
            if ($status !== 'todo') {
                $tasks->updateStatus($taskId, $status);
            }
        }

        // A couple of defects per project.
        for ($i = 0; $i < 2; $i++) {
            $assignee = $memberIds ? (int)$memberIds[array_rand($memberIds)] : null;
            $defects->create([
                'project_id' => $projectId,
                'code' => 'DEF-' . str_pad((string)$defectSeq, 3, '0', STR_PAD_LEFT),
                'title' => 'Sample defect #' . $defectSeq . ' for ' . $projectName,
                'description' => 'Auto-generated demo defect for walkthrough purposes.',
                'severity' => $severities[array_rand($severities)],
                'status' => $defectStatuses[array_rand($defectStatuses)],
                'assigned_to' => $assignee,
                'reported_by' => $mgrId,
            ]);
            $defectSeq++;
        }
    }
}

echo "\nDone. Every seeded user's password is: Test1234!\n";
echo "Log in as a manager with their employee code, or as admin (admin@bel.co.in / admin123) to browse everything.\n";
