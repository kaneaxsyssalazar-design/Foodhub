<?php
session_start();

// Access Control: Allow ANY logged-in user (admin or standard) to post
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=must_login');
    exit;
}

require_once 'db_connect.php';

// Check admin status for navigation bar context
$is_admin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)
         || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')
         || (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

$error = '';
$success = '';

// Pre-fill variable placeholders for Sticky Form inputs
$title = '';
$country_of_origin = '';
$category_id = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $country_of_origin = trim($_POST['country_of_origin'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    // Retrieve formatted HTML content directly from TinyMCE
    $content = trim($_POST['content'] ?? '');

    // 1. Validate Text Inputs
    if (empty($title) || empty($country_of_origin) || empty($content) || !$category_id) {
        $error = 'All fields (Title, Origin, Category, and Content) are required.';
    } 

    // 2. Process Image Upload if Text Inputs are Valid
    $image_path = null;
    
    if (empty($error) && isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['food_image']['tmp_name'];
        $file_name = $_FILES['food_image']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // MIME Type verification for enhanced security
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (in_array($file_ext, $allowed_exts) && in_array($mime_type, $allowed_mimes)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
            $target_file   = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $image_path = $target_file;
            } else {
                $error = 'Failed to save food image.';
            }
        } else {
            $error = 'Invalid image type. Allowed formats: JPG, JPEG, PNG, GIF, WEBP.';
        }
    } elseif (empty($error) && (!isset($_FILES['food_image']) || $_FILES['food_image']['error'] !== UPLOAD_ERR_OK)) {
        $error = 'Please select a valid image file to upload.';
    }

    // 3. Save to Database with user_id ownership & Redirect
    if (empty($error)) {
        $stmt = $db->prepare('
            INSERT INTO pages (title, country_of_origin, category_id, image_path, content, user_id, created_at, updated_at) 
            VALUES (:title, :country, :category_id, :image_path, :content, :user_id, NOW(), NOW())
        ');
        
        $result = $stmt->execute([
            ':title'        => $title,
            ':country'      => $country_of_origin,
            ':category_id'  => $category_id,
            ':image_path'   => $image_path,
            ':content'      => $content,
            ':user_id'      => $_SESSION['user_id']
        ]);

        if ($result) {
            header('Location: index.php?msg=dish_created');
            exit;
        } else {
            $error = 'Failed to publish post. Please try again.';
        }
    }
}

// Fetch categories for drop-down menu
$category_stmt = $db->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a New Dish - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=99">
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

        .container {
            max-width: 800px;
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

        h2 {
            color: #38bdf8;
            margin-top: 0;
            margin-bottom: 25px;
            border-bottom: 1px solid #27272a;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
            color: #e4e4e7;
        }

        .form-control {
            width: 100%;
            background: #27272a;
            color: #fff;
            border: 1px solid #3f3f46;
            padding: 10px 12px;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #0284c7;
        }

        .file-input-box {
            background: #18181b;
            border: 1px dashed #3f3f46;
            padding: 15px;
            border-radius: 6px;
        }

        .btn-submit {
            background: #0284c7;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
        }

        .btn-submit:hover {
            background: #0369a1;
        }

        .btn-cancel {
            margin-left: 15px;
            color: #a1a1aa;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-cancel:hover {
            color: #f4f4f5;
        }

        .alert-error {
            color: #fca5a5;
            background: #451a1a;
            border: 1px solid #7f1d1d;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: bold;
        }
    </style>

    <!-- TinyMCE WYSIWYG Editor Script -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
    <script>
        tinymce.init({
            selector: '#content',
            height: 350,
            skin: 'oxide-dark',
            content_css: 'dark',
            menubar: false,
            plugins: 'lists link image code table wordcount',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code',
            entity_encoding: 'raw',
            encoding: 'html',
            verify_html: true
        });
    </script>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a>
            <a href="create_page.php">+ Post Dish</a>
            <?php if ($is_admin): ?>
                <a href="manage_pages.php">⚙️ Manage Pages</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <div class="container">
        <a href="index.php" class="back-link">← Back to Home</a>

        <h2>Post a Dish to Food Hub</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="create_page.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Dish Name / Title:</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($title) ?>" placeholder="e.g., Authentic Chicken Adobo" class="form-control">
            </div>

            <div class="form-group">
                <label for="country_of_origin">Country / Region of Origin:</label>
                <input type="text" id="country_of_origin" name="country_of_origin" required value="<?= htmlspecialchars($country_of_origin) ?>" placeholder="e.g., Philippines" class="form-control">
            </div>

            <div class="form-group">
                <label for="category_id">Food Category:</label>
                <select id="category_id" name="category_id" required class="form-control">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ((int)$category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="food_image">Food Photo:</label>
                <div class="file-input-box">
                    <input type="file" id="food_image" name="food_image" accept="image/*" required style="color: #e4e4e7;">
                </div>
            </div>

            <div class="form-group">
                <label for="content">Story, Recipe, or Description:</label>
                <textarea id="content" name="content" rows="10" placeholder="Write your dish story, ingredients, or recipe steps here..." class="form-control"><?= htmlspecialchars(html_entity_decode($content ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div style="margin-top: 25px;">
                <button type="submit" class="btn-submit">Share Dish</button>
                <a href="index.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>

</body>
</html>