<?php
session_start();
require_once 'db_connect.php';

// Guard: Admin access only
if (empty($_SESSION['is_admin'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$message = '';

// --- HANDLE MODERATION ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['comment_id'])) {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    $page_id    = filter_input(INPUT_POST, 'page_id', FILTER_VALIDATE_INT);
    $action     = $_POST['action'];

    if ($comment_id) {
        if ($action === 'delete') {
            // Delete comment completely
            $stmt = $db->prepare('DELETE FROM comments WHERE id = :id');
            $stmt->execute([':id' => $comment_id]);
            $message = 'Comment permanently deleted.';

            // If the deletion request came directly from post.php, redirect back there
            if ($page_id) {
                header("Location: post.php?id={$page_id}");
                exit;
            }
        } elseif ($action === 'disemvowel') {
            // Fetch comment, strip vowels, update record
            $stmt = $db->prepare('SELECT comment_text FROM comments WHERE id = :id');
            $stmt->execute([':id' => $comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($comment) {
                // Remove uppercase and lowercase vowels
                $disemvoweled = preg_replace('/[aeiouAEIOU]/', '', $comment['comment_text']);
                
                $update = $db->prepare('UPDATE comments SET comment_text = :text WHERE id = :id');
                $update->execute([':text' => $disemvoweled, ':id' => $comment_id]);
                $message = 'Comment disemvoweled successfully!';
            }
        }
    }
}

// --- FETCH COMMENTS WITH POST TITLES ---
$query = '
    SELECT c.*, p.title AS post_title 
    FROM comments c
    LEFT JOIN pages p ON c.page_id = p.id
    ORDER BY c.created_at DESC
';
$comments = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Comments - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=5">
    <style>
        body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
        }

        .mod-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 24px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            color: #0f172a;
        }

        .mod-container h2 {
            color: #0f172a !important;
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }

        .mod-container p {
            color: #475569 !important;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }

        .mod-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .mod-table th {
            background: #f1f5f9;
            color: #0f172a !important;
            font-weight: 600;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
        }
        .mod-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            color: #334155 !important;
            vertical-align: top;
        }
        .mod-table tr:hover { 
            background-color: #f8fafc; 
        }

        .btn-danger {
            background: #ef4444;
            color: #ffffff !important;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-warning {
            background: #f59e0b;
            color: #ffffff !important;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-right: 6px;
        }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning:hover { background: #d97706; }
    </style>
</head>
<body>

    <header style="background: #1e293b; color: #fff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center;">
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php" style="color: #fff; text-decoration: none; margin-right: 10px;">🏠 Home</a> | 
            <a href="manage_pages.php" style="color: #fff; text-decoration: none; margin: 0 10px;">📋 Manage Pages</a> | 
            <a href="manage_categories.php" style="color: #fff; text-decoration: none; margin: 0 10px;">🏷️ Categories</a> | 
            <a href="logout.php" style="color: #ef4444; text-decoration: none; margin-left: 10px;">Logout</a>
        </div>
    </header>

    <div class="mod-container">
        <h2>Comment Moderation</h2>
        <p>Review user comments, disemvowel inappropriate text, or remove offensive posts.</p>

        <?php if ($message): ?>
            <p style="color: #166534; font-weight: bold; background: #dcfce7; padding: 12px; border-radius: 6px; border: 1px solid #bbf7d0;">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <table class="mod-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Author</th>
                    <th style="width: 35%;">Comment Text</th>
                    <th style="width: 20%;">Associated Dish</th>
                    <th style="width: 25%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php 
                            $author = $comment['author_name'] ?? $comment['author'] ?? 'Guest'; 
                            $post_link_id = $comment['page_id'] ?? 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($author) ?></strong><br>
                                <small style="color: #64748b;"><?= date('M j, Y g:i a', strtotime($comment['created_at'])) ?></small>
                            </td>
                            <td><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></td>
                            <td>
                                <a href="post.php?id=<?= $post_link_id ?>" style="color: #2563eb; text-decoration: none; font-weight: 500;" target="_blank">
                                    <?= htmlspecialchars($comment['post_title'] ?? 'Unknown Dish') ?> ↗
                                </a>
                            </td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <input type="hidden" name="action" value="disemvowel">
                                    <button type="submit" class="btn-warning" onclick="return confirm('Disemvowel this comment?');">
                                        ✂️ Disemvowel
                                    </button>
                                </form>

                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Delete this comment permanently?');">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b;">No comments found in the database.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>