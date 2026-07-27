<?php
require_once 'includes/bootstrap.php';

use App\Repositories\UserRepository;

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginAs = $_POST['login_as'] ?? '';

    if ($email === '' || $password === '' || $loginAs === '') {
        $error = 'Please select a role and enter both email and password.';
    } else {
        $user = (new UserRepository())->findByEmail($email);

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
                ];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
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
                <select id="login_as" name="login_as" required onchange="document.getElementById('email_label').textContent = this.value === 'admin' ? 'Admin ID' : (this.value === 'user' ? 'User ID' : 'ID');">
                    <option value="">Select&hellip;</option>
                    <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                </select>
            </div>
            <div class="field">
                <label id="email_label" for="email"><?= $selectedRole === 'admin' ? 'Admin ID' : ($selectedRole === 'user' ? 'User ID' : 'ID') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
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
