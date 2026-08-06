<?php
session_start();
require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        // Unique placeholders (:username and :email) resolve PDO HY093
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute([
            ':username' => $login_input,
            ':email'    => $login_input
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password hash against database record
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];

            // Redirect with a success flag
            header('Location: index.php?login=success');
            exit;
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Food Hub Portal</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .auth-card { max-width: 400px; margin: 80px auto; padding: 30px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .btn-submit { width: 100%; background: #0284c7; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-submit:hover { background: #0369a1; }
        .alert-error { color: #dc2626; font-weight: bold; margin-bottom: 15px; }
        .alert-success { color: #16a34a; font-weight: bold; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="auth-card">
        <h2>Log In</h2>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert-success">Account created! You can now log in.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" placeholder="Enter username or email" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">Log In</button>
        </form>
        <p style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
            Don't have an account? <a href="register.php" style="color: #0284c7;">Sign up here</a>
        </p>
    </div>

</body>
</html>