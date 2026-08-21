<?php
require_once 'includes/bootstrap.php';

use App\Repositories\UserRepository;

if (current_user()) {
    header('Location: ' . (current_user()['role'] === 'admin' ? 'employees.php' : 'projects.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginAs = $_POST['login_as'] ?? '';

    if ($identifier === '' || $password === '' || $loginAs === '') {
        $error = 'Please select a role and enter both ID and password.';
    } else {
        $users = new UserRepository();
        // Admins log in with email; employees/managers log in with their staff ID.
        $user = $loginAs === 'admin' ? $users->findByEmail($identifier) : $users->findByEmployeeCode($identifier);

        if ($user && password_verify($password, $user['password'])) {
            $userCategory = $user['role'] === 'admin' ? 'admin' : 'user';
            if ($userCategory !== $loginAs) {
                $error = 'This account is registered as ' . ($userCategory === 'admin' ? 'Admin' : 'User') . '. Please select the correct option.';
            } else {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'employee_code' => $user['employee_code'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'department' => $user['department'],
                    'has_photo' => !empty($user['photo_filename']),
                    'must_change_password' => !empty($user['must_change_password']),
                ];
                header('Location: ' . ($user['role'] === 'admin' ? 'employees.php' : 'projects.php'));
                exit;
            }
        } else {
            $error = 'Invalid ID or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log In — BEL Project Management System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <svg class="login-wave" viewBox="0 0 1440 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0,0 L1440,0 L1440,320 C1160,400 980,140 760,220 C500,315 180,140 0,320 Z" fill="#1a6faa"/>
    </svg>
    <div class="login-card">
        <img class="login-logo" src="assets/img/bel-logo.png" alt="Bharat Electronics Limited">

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php $selectedRole = $_POST['login_as'] ?? ''; ?>
        <form method="POST" action="login.php">
            <div class="field">
                <select id="login_as" name="login_as" required onchange="
                    document.getElementById('email_label').textContent = this.value === 'admin' ? 'Admin ID' : (this.value === 'user' ? 'User ID' : 'ID');
                    var idInput = document.getElementById('email');
                    idInput.type = this.value === 'admin' ? 'email' : 'text';
                    idInput.placeholder = this.value === 'admin' ? 'admin@bel.co.in' : 'e.g. BEL0031';
                ">
                    <option value="" disabled<?= $selectedRole === '' ? ' selected' : '' ?>>Select</option>
                    <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                </select>
            </div>
            <div class="field">
                <label id="email_label" for="email"><?= $selectedRole === 'admin' ? 'Admin ID' : ($selectedRole === 'user' ? 'User ID' : 'ID') ?></label>
                <input type="<?= $selectedRole === 'admin' ? 'email' : 'text' ?>" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="<?= $selectedRole === 'admin' ? 'admin@bel.co.in' : 'e.g. BEL0031' ?>" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-block">Log In</button>
        </form>
    </div>
</div>
</body>
</html>
