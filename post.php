<?php
session_start();
require_once 'db_connect.php';

$page_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$page_id) {
    header('Location: index.php');
    exit;
}

// Fetch Page Details
$stmt = $db->prepare('
    SELECT p.*, c.name AS category_name 
    FROM pages p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = :id
');
$stmt->execute([':id' => $page_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die('Post not found.');
}

// EXPANDED ADMIN SESSION CHECK
$is_admin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)
         || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')
         || (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

$is_author = isset($_SESSION['user_id']) && isset($post['user_id']) && ((int)$post['user_id'] === (int)$_SESSION['user_id']);
$can_manage_post = $is_admin || $is_author;

// --- 1. HANDLE POST DELETION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    if ($can_manage_post) {
        $del_comments = $db->prepare('DELETE FROM comments WHERE page_id = :id');
        $del_comments->execute([':id' => $page_id]);

        $del_post = $db->prepare('DELETE FROM pages WHERE id = :id');
        $del_post->execute([':id' => $page_id]);

        header('Location: manage_pages.php?message=deleted');
        exit;
    }
}

// --- 2. HANDLE COMMENT DELETION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);

    if ($comment_id) {
        // ADMIN DIRECT DELETE
        if ($is_admin) {
            $del_stmt = $db->prepare('DELETE FROM comments WHERE id = :id');
            $del_stmt->execute([':id' => $comment_id]);

            header("Location: post.php?id=" . $page_id);
            exit;
        }

        // NON-ADMIN DELETE OWN COMMENT
        $stmt_com = $db->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt_com->execute([':id' => $comment_id]);
        $target_comment = $stmt_com->fetch(PDO::FETCH_ASSOC);

        if ($target_comment) {
            $author = $target_comment['author_name'] ?? $target_comment['author'] ?? '';
            $is_comment_owner = isset($_SESSION['username']) && ($_SESSION['username'] === $author);

            if ($is_comment_owner) {
                $del_stmt = $db->prepare('DELETE FROM comments WHERE id = :id');
                $del_stmt->execute([':id' => $comment_id]);

                header("Location: post.php?id=" . $page_id);
                exit;
            }
        }
    }
}

$error = '';
$success = '';

$author_name = $_SESSION['username'] ?? '';
$comment_text = '';

// --- 3. HANDLE COMMENT SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $author_name  = trim($_POST['author_name'] ?? '');
    $comment_text = trim($_POST['comment_text'] ?? '');
    $user_captcha = strtoupper(trim($_POST['captcha_input'] ?? ''));

    $session_captcha = $_SESSION['captcha'] ?? '';

    if (empty($author_name) || empty($comment_text)) {
        $error = 'Name and comment text are required.';
    } elseif (empty($user_captcha) || $user_captcha !== $session_captcha) {
        $error = 'Incorrect CAPTCHA answer. Please try again.';
    } else {
        $insert_stmt = $db->prepare('
            INSERT INTO comments (page_id, author_name, comment_text, created_at) 
            VALUES (:page_id, :author_name, :comment_text, NOW())
        ');
        $insert_stmt->execute([
            ':page_id'      => $page_id,
            ':author_name'  => $author_name,
            ':comment_text' => $comment_text
        ]);
        
        unset($_SESSION['captcha']);
        $comment_text = '';
        $success = 'Comment submitted successfully!';
    }
}

// Fetch Comments
$comment_stmt = $db->prepare('SELECT * FROM comments WHERE page_id = :page_id AND (is_hidden IS NULL OR is_hidden = 0) ORDER BY created_at DESC');
$comment_stmt->execute([':page_id' => $page_id]);
$comments = $comment_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=99">
    <!-- Lightbox2 CSS for Image Zooming Overlay -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    <style>
        body {
            background-color: #121212;
            color: #f4f4f5;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        header {
            background: #18181b;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #27272a;
        }

        header a {
            color: #38bdf8;
            text-decoration: none;
            margin-left: 12px;
            font-weight: bold;
        }

        header a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: #1c1c1e;
            border: 1px solid #27272a;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .back-link {
            color: #38bdf8;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        h1 {
            color: #38bdf8;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .image-wrapper {
            display: block;
            margin: 20px 0;
            cursor: zoom-in;
            position: relative;
        }

        .post-image {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #27272a;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .image-wrapper:hover .post-image {
            opacity: 0.9;
            transform: scale(1.005);
        }

        .badge-container {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            background: #27272a;
            color: #e4e4e7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .admin-toolbar {
            background: #18181b;
            border: 1px solid #27272a;
            padding: 12px 18px;
            border-radius: 6px;
            margin: 20px 0;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-edit-post {
            background: #0284c7;
            color: #fff !important;
            padding: 6px 14px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .btn-edit-post:hover {
            background: #0369a1;
        }

        .btn-delete-post {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .btn-delete-post:hover {
            background: #dc2626;
        }

        .post-body {
            margin-top: 25px;
            font-size: 1.05rem;
            line-height: 1.7;
            color: #e4e4e7;
        }

        hr {
            margin: 35px 0;
            border: none;
            border-top: 1px solid #27272a;
        }

        .section-header {
            color: #38bdf8;
            margin-bottom: 20px;
        }

        .comment-box {
            background: #18181b;
            border: 1px solid #27272a;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .comment-content {
            flex-grow: 1;
        }

        .comment-meta {
            font-size: 0.85rem;
            color: #a1a1aa;
            margin-bottom: 6px;
        }

        .btn-delete-comment {
            background: #451a1a;
            color: #fca5a5;
            border: 1px solid #7f1d1d;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .btn-delete-comment:hover {
            background: #ef4444;
            color: #fff;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #e4e4e7;
        }

        .form-control {
            width: 100%;
            background: #27272a;
            color: #fff;
            border: 1px solid #3f3f46;
            padding: 10px;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #0284c7;
        }

        .captcha-box {
            background: #18181b;
            padding: 18px;
            border-radius: 6px;
            border: 1px solid #27272a;
            margin-bottom: 20px;
            max-width: 420px;
        }

        .btn-submit {
            background: #0284c7;
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.95rem;
        }

        .btn-submit:hover {
            background: #0369a1;
        }

        .alert-error {
            color: #fca5a5;
            background: #451a1a;
            border: 1px solid #7f1d1d;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            color: #86efac;
            background: #14532d;
            border: 1px solid #166534;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="create_page.php">+ Post Dish</a>
                <?php if ($is_admin): ?>
                    <a href="manage_pages.php">⚙️ Manage Pages</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <a href="index.php" class="back-link">← Back to All Dishes</a>

        <h1><?= htmlspecialchars($post['title']) ?></h1>
        
        <!-- ADMIN / AUTHOR TOOLBAR -->
        <?php if ($can_manage_post): ?>
            <div class="admin-toolbar">
                <span style="font-weight: bold; font-size: 0.9rem; color: #a1a1aa;">Management Options:</span>
                <a href="edit_page.php?id=<?= $post['id'] ?>" class="btn-edit-post">✏️ Edit Page</a>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_post">
                    <button type="submit" class="btn-delete-post" onclick="return confirm('Are you sure you want to delete this page permanently?');">
                        🗑️ Delete Page
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="badge-container">
            <span class="badge"><?= htmlspecialchars($post['category_name'] ?? 'Uncategorized') ?></span>
            <?php if (!empty($post['country_of_origin'])): ?>
                <span class="badge"><?= htmlspecialchars($post['country_of_origin']) ?></span>
            <?php endif; ?>
            
            <?php if (!empty($post['created_at'])): ?>
                <span style="font-size: 0.85rem; color: #a1a1aa; margin-left: 10px;">
                    📅 Published on <?= date('F j, Y \a\t g:i A', strtotime($post['created_at'])) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- LIGHTVIEW / LIGHTBOX IMAGE ZOOM -->
        <?php if (!empty($post['image_path']) && file_exists($post['image_path'])): ?>
            <a href="<?= htmlspecialchars($post['image_path']) ?>" data-lightbox="dish-image" data-title="<?= htmlspecialchars($post['title']) ?>" class="image-wrapper">
                <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="post-image">
            </a>
        <?php endif; ?>

        <div class="post-body">
            <?= $post['content'] ?>
        </div>

        <hr>

        <h3 class="section-header">💬 Comments (<?= count($comments) ?>)</h3>

        <?php if (count($comments) > 0): ?>
            <?php foreach ($comments as $com): ?>
                <?php 
                    $comment_author = $com['author_name'] ?? $com['author'] ?? 'Guest';
                    $is_comment_owner = isset($_SESSION['username']) && ($_SESSION['username'] === $comment_author);
                    $can_delete_comment = $is_admin || $is_comment_owner;
                ?>
                <div class="comment-box">
                    <div class="comment-content">
                        <div class="comment-meta">
                            <strong style="color: #38bdf8;"><?= htmlspecialchars($comment_author) ?></strong> 
                            • <em><?= date('M j, Y \a\t g:i A', strtotime($com['created_at'])) ?></em>
                        </div>
                        <p style="margin: 5px 0 0 0; color: #e4e4e7;"><?= nl2br(htmlspecialchars($com['comment_text'])) ?></p>
                    </div>

                    <?php if ($can_delete_comment): ?>
                        <form action="post.php?id=<?= $page_id ?>" method="POST" style="margin-left: 15px;">
                            <input type="hidden" name="comment_id" value="<?= $com['id'] ?>">
                            <input type="hidden" name="action" value="delete_comment">
                            <button type="submit" class="btn-delete-comment" onclick="return confirm('Are you sure you want to delete this comment?');">
                                🗑️ Delete
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #a1a1aa;">No comments yet. Be the first to share your thoughts!</p>
        <?php endif; ?>

        <hr>

        <h3 class="section-header">Leave a Comment</h3>
        
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="post.php?id=<?= $page_id ?>">
            <div class="form-group">
                <label for="author_name">Your Name:</label>
                <input type="text" id="author_name" name="author_name" class="form-control" style="max-width: 400px;" value="<?= htmlspecialchars($author_name) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="comment_text">Comment:</label>
                <textarea id="comment_text" name="comment_text" rows="4" class="form-control" required><?= htmlspecialchars($comment_text) ?></textarea>
            </div>

            <div class="captcha-box">
                <label for="captcha_input" style="font-weight: bold; display: block; margin-bottom: 8px; color: #e4e4e7;">Security Verification:</label>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                    <img src="captcha.php?r=<?= rand() ?>" alt="CAPTCHA Code" style="border: 1px solid #3f3f46; border-radius: 4px;">
                    <input type="text" id="captcha_input" name="captcha_input" class="form-control" required placeholder="Enter code" style="width: 140px;" autocomplete="off">
                </div>
                <small style="color: #a1a1aa;">Prove you are human before posting your comment.</small>
            </div>

            <button type="submit" class="btn-submit">Submit Comment</button>
        </form>
    </div>

    <!-- Lightbox2 JavaScript for Lightview Image Overlay -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>
</body>
</html>