<?php
session_start();
require_once 'db_connect.php';

// Guard: Only logged-in users can access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=must_login');
    exit;
}

// 1. Admin Verification
$is_admin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)
         || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')
         || (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

// 2. Validate GET Page ID
$page_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$page_id) {
    header('Location: manage_pages.php');
    exit;
}

// 3. Fetch current page details
$stmt = $db->prepare('SELECT * FROM pages WHERE id = :id');
$stmt->execute([':id' => $page_id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    die('Post not found.');
}

// 4. Authorization check: Must be Admin OR Owner
$is_owner = ((int)$page['user_id'] === (int)$_SESSION['user_id']);
if (!$is_admin && !$is_owner) {
    die('Unauthorized access: You can only edit your own posts.');
}

// Fetch categories for the select list
$cat_stmt = $db->query('SELECT * FROM categories ORDER BY name ASC');
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;

    if (empty($title) || empty($content)) {
        $error = 'Title and content cannot be empty.';
    } else {
        $update_stmt = $db->prepare('
            UPDATE pages 
            SET title = :title, content = :content, category_id = :cat_id, updated_at = NOW() 
            WHERE id = :id
        ');
        $update_stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':cat_id' => $category_id,
            ':id' => $page_id
        ]);

        header('Location: manage_pages.php?message=updated');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=5">
    <style>
        body { background-color: #121212; color: #f4f4f5; font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { background: #18181b; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #27272a; }
        header a { color: #38bdf8; text-decoration: none; margin-left: 10px; }
        .edit-container { max-width: 700px; margin: 40px auto; background: #1c1c1e; padding: 25px; border-radius: 8px; border: 1px solid #27272a; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        label { display: block; margin-top: 15px; font-weight: bold; color: #e4e4e7; }
        input[type="text"], select, textarea { width: 100%; padding: 10px; margin-top: 6px; background: #27272a; border: 1px solid #3f3f46; color: #fff; border-radius: 4px; box-sizing: border-box; font-size: 1rem; }
        textarea { resize: vertical; }
        .btn-submit { background: #0284c7; color: #fff; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 20px; font-size: 1rem; }
        .btn-submit:hover { background: #0369a1; }
        .btn-cancel { color: #a1a1aa; text-decoration: none; margin-left: 15px; font-size: 0.95rem; }
        .btn-cancel:hover { text-decoration: underline; color: #fff; }
        .alert-error { background: #7f1d1d; color: #fca5a5; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>

    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a> | 
            <a href="manage_pages.php">📋 Manage Pages</a> | 
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <div class="edit-container">
        <h2>✏️ Edit Post</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_page.php?id=<?= $page['id'] ?>">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($page['title']) ?>" required>

            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $page['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="content">Content</label>
            <textarea id="content" name="content" rows="10" required><?= htmlspecialchars($page['content']) ?></textarea>

            <div>
                <button type="submit" class="btn-submit">Update Post</button>
                <a href="manage_pages.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>

</body>
</html>