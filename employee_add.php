<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\UserRepository;

$page_title = 'Add Employee';
$u = current_user();
$error = '';
$users = new UserRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_code = trim($_POST['employee_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $department = trim($_POST['department'] ?? '');
    $stream = trim($_POST['stream'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $user_group = trim($_POST['user_group'] ?? '');
    $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

    if ($employee_code === '' || $name === '' || $email === '' || $password === '') {
        $error = 'Staff ID, name, email and password are required.';
    } elseif (!in_array($role, ['admin', 'manager', 'employee'], true)) {
        $error = 'Invalid role selected.';
    } elseif ($users->emailOrCodeExists($email, $employee_code)) {
        $error = 'An employee with this email or staff ID already exists.';
    } else {
        $id = $users->create([
            'employee_code' => $employee_code,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'department' => $department,
            'manager_id' => $manager_id,
            'stream' => $stream,
            'telephone' => $telephone,
            'user_group' => $user_group,
        ]);

        header('Location: employee_detail.php?id=' . $id);
        exit;
    }
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js"></script>

<div class="breadcrumb"><a href="employees.php">Employees</a> / Add Employee</div>

<div class="panel" style="max-width:760px;">
    <div class="panel-head"><h3>Employee Details</h3></div>
    <div class="panel-body">
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="employee_add.php">
            <div class="form-grid">
                <div class="field">
                    <label>Staff ID</label>
                    <input type="text" name="employee_code" placeholder="BEL0002" required
                           value="<?= htmlspecialchars($_POST['employee_code'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Temporary Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="field">
                    <label>Department</label>
                    <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Role</label>
                    <select name="role">
                        <option value="employee" <?= ($_POST['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                        <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="field">
                    <label>Stream</label>
                    <input type="text" name="stream" value="<?= htmlspecialchars($_POST['stream'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Telephone</label>
                    <input type="text" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Group</label>
                    <input type="text" name="user_group" value="<?= htmlspecialchars($_POST['user_group'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Reports To (Manager)</label>
                    <div id="managerPicker"></div>
                </div>
            </div>
            <button type="submit" class="btn">Create Employee</button>
            <a href="employees.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managerRoot = document.getElementById('managerPicker');
    managerRoot.innerHTML = empPickerHTML('manager_id', 'Search name or employee ID…');
    initEmpPicker(managerRoot, { roles: ['admin', 'manager'] });
});
</script>

<?php require 'includes/layout_bottom.php'; ?>
