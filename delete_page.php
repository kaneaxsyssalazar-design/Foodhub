<?php
session_start();

// 1. Access Control: Ensure the user is logged in first
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    // 2. Fetch image path AND user_id to verify ownership
    $stmt = $db->prepare('SELECT image_path, user_id FROM pages WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        // 3. AUTHORIZATION CHECK: Must be Admin OR the Post Owner
        $is_admin = !empty($_SESSION['is_admin']);
        $is_owner = ($page['user_id'] == $_SESSION['user_id']);

        if (!$is_admin && !$is_owner) {
            http_response_code(403);
            die("<h1>403 Forbidden</h1><p>You do not have permission to delete this post.</p><p><a href='index.php'>Return to Home</a></p>");
        }

        // 4. Delete the uploaded image file if it exists
        if (!empty($page['image_path']) && file_exists($page['image_path'])) {
            unlink($page['image_path']);
        }

        // 5. Delete page record from database
        $delete_stmt = $db->prepare('DELETE FROM pages WHERE id = :id');
        $delete_stmt->execute([':id' => $id]);
    }
}

// Redirect back to dashboard/home view
header('Location: index.php?msg=deleted');
exit;