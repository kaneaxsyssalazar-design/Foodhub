<?php
session_start();
require_once 'db_connect.php';

// Guard: Only logged-in users can access this list
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=must_login');
    exit;
}

// Admin check
$is_admin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)
         || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')
         || (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

// --- POST DELETION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_page') {
    $page_id = filter_input(INPUT_POST, 'page_id', FILTER_VALIDATE_INT);

    if ($page_id) {
        $stmt_check = $db->prepare('SELECT user_id FROM pages WHERE id = :id');
        $stmt_check->execute([':id' => $page_id]);
        $page_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($page_data) {
            $is_owner = ((int)$page_data['user_id'] === (int)$_SESSION['user_id']);

            if ($is_admin || $is_owner) {
                // Delete associated comments first to avoid foreign key errors
                $del_comments = $db->prepare('DELETE FROM comments WHERE page_id = :id');
                $del_comments->execute([':id' => $page_id]);

                // Delete page
                $del_page = $db->prepare('DELETE FROM pages WHERE id = :id');
                $del_page->execute([':id' => $page_id]);

                header('Location: manage_pages.php?message=deleted');
                exit;
            } else {
                die('Unauthorized: You do not have permission to delete this post.');
            }
        }
    }
}

// Sorting configuration
$allowed_columns = [
    'title'      => 'Title',
    'created_at' => 'Date Created',
    'updated_at' => 'Date Updated'
];

$sort_col = $_GET['sort'] ?? 'created_at';
$sort_dir = strtoupper($_GET['dir'] ?? 'DESC');

if (!array_key_exists($sort_col, $allowed_columns)) {
    $sort_col = 'created_at';
}
if (!in_array($sort_dir, ['ASC', 'DESC'])) {
    $sort_dir = 'DESC';
}

function getSortUrl($col, $current_col, $current_dir) {
    $next_dir = ($col === $current_col && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    return "manage_pages.php?sort={$col}&dir={$next_dir}";
}

// Query pages list
$sql = "SELECT p.*, c.name AS category_name 
        FROM pages p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY {$sort_col} {$sort_dir}";

$stmt = $db->prepare($sql);
$stmt->execute();
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=5">
    <style>
        body { background-color: #121212; color: #f4f4f5; font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { background: #18181b; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #27272a; }
        header a { color: #38bdf8; text-decoration: none; margin-left: 10px; }
        .sort-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #1c1c1e; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .sort-table th, .sort-table td { padding: 14px 18px; text-align: left; border-bottom: 1px solid #27272a; color: #e4e4e7; }
        .sort-table th { background: #27272a; color: #ffffff; font-weight: bold; }
        .sort-table th a { color: #ffffff; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .sort-table th a:hover { color: #38bdf8; }
        .sort-table tbody tr:hover { background-color: #242427; }
        .active-sort { color: #38bdf8; font-weight: bold; }
        .badge-sort { background: #0284c7; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; }
        .action-link-edit { color: #38bdf8; text-decoration: none; margin-right: 12px; font-weight: bold; }
        .action-link-edit:hover { text-decoration: underline; }
        .btn-delete-link { background: none; border: none; color: #f87171; font-weight: bold; cursor: pointer; padding: 0; font-size: 1rem; font-family: inherit; }
        .btn-delete-link:hover { text-decoration: underline; }
        .text-muted { color: #71717a; font-size: 0.85rem; font-style: italic; }
        .alert-success { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; padding: 12px 18px; border-radius: 6px; margin-top: 15px; font-weight: bold; }
    </style>
</head>
<body>

    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a> | 
            <a href="create_page.php">+ Post Dish</a> | 
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <div style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        <h2>Manage & Sort Pages</h2>
        
        <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
            <div class="alert-success">✓ Page deleted successfully!</div>
        <?php elseif (isset($_GET['message']) && $_GET['message'] === 'updated'): ?>
            <div class="alert-success">✓ Page updated successfully!</div>
        <?php endif; ?>

        <div style="background: #18181b; border: 1px solid #27272a; padding: 12px 18px; border-radius: 6px; margin-top: 15px; font-size: 0.95rem;">
            📌 Currently sorted by: 
            <span class="badge-sort">
                <?= $allowed_columns[$sort_col] ?> (<?= $sort_dir === 'ASC' ? 'Ascending ▲' : 'Descending ▼' ?>)
            </span>
        </div>

        <table class="sort-table">
            <thead>
                <tr>
                    <th>
                        <a href="<?= getSortUrl('title', $sort_col, $sort_dir) ?>">
                            Title 
                            <?php if ($sort_col === 'title'): ?>
                                <span class="active-sort"><?= $sort_dir === 'ASC' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <th>
                        <a href="<?= getSortUrl('created_at', $sort_col, $sort_dir) ?>">
                            Created At 
                            <?php if ($sort_col === 'created_at'): ?>
                                <span class="active-sort"><?= $sort_dir === 'ASC' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= getSortUrl('updated_at', $sort_col, $sort_dir) ?>">
                            Updated At 
                            <?php if ($sort_col === 'updated_at'): ?>
                                <span class="active-sort"><?= $sort_dir === 'ASC' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pages)): ?>
                    <?php foreach ($pages as $p): ?>
                        <?php 
                            $is_owner = isset($_SESSION['user_id']) && isset($p['user_id']) && ((int)$p['user_id'] === (int)$_SESSION['user_id']);
                            $can_modify = $is_admin || $is_owner;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                            <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= !empty($p['created_at']) ? date('F j, Y, g:i a', strtotime($p['created_at'])) : 'N/A' ?></td>
                            <td><?= !empty($p['updated_at']) ? date('F j, Y, g:i a', strtotime($p['updated_at'])) : 'N/A' ?></td>
                            <td>
                                <?php if ($can_modify): ?>
                                    <a href="edit_page.php?id=<?= $p['id'] ?>" class="action-link-edit">Edit</a>
                                    
                                    <form method="POST" action="manage_pages.php" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_page">
                                        <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-delete-link" onclick="return confirm('Are you sure you want to delete this page?');">
                                            Delete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Read only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #a1a1aa;">
                            No pages found in database.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>