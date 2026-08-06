<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Securely hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user with email and hashed password
            $insert_stmt = $db->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
            $insert_stmt->execute([
                ':email' => $email,
                ':password' => $hashed_password
            ]);

            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Food Hub Portal</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .auth-card { max-width: 400px; margin: 80px auto; padding: 30px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .btn-submit { width: 100%; background: #0284c7; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-submit:hover { background: #0369a1; }
        .alert-error { color: #dc2626; font-weight: bold; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="auth-card">
        <h2>Create an Account</h2>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">Sign Up</button>
        </form>
        <p style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
            Already have an account? <a href="login.php" style="color: #0284c7;">Log in here</a>
        </p>
    </div>

</body>
</html>