<?php
session_start();

// 1. Unset all session variables in memory
$_SESSION = array();

// 2. Clear the session cookie on the user's browser if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect with a status flag to confirm logout to the user
header('Location: index.php?logout=success');
exit;