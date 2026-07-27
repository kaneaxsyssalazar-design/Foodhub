<?php
session_start();
require_once 'db_connect.php';

// Guard: Only logged-in users can manage categories
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=must_login');
    exit;
}

$error = '';
$success = '';
$edit_category = null;

// --- 1. HANDLE ADD / UPDATE CATEGORY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    if (empty($name)) {
        $error = 'Category name cannot be empty.';
    } else {
        if ($category_id) {
            // Update existing category
            $stmt = $db->prepare('UPDATE categories SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $category_id]);
            $success = 'Category updated successfully!';
        } else {
            // Create new category
            $stmt = $db->prepare('INSERT INTO categories (name) VALUES (:name)');
            $stmt->execute([':name' => $name]);
            $success = 'Category added successfully!';
        }
    }
}

// --- 2. HANDLE EDIT MODE FETCH ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $cat_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($cat_id) {
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id' => $cat_id]);
        $edit_category = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// --- 3. FETCH ALL CATEGORIES ---
$categories = $db->query('SELECT c.*, COUNT(p.id) AS total_posts FROM categories c LEFT JOIN pages p ON c.id = p.category_id GROUP BY c.id ORDER BY c.name ASC')->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=5">
    <style>
    /* Main Layout */
    .cat-layout {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 30px;
        margin-top: 20px;
    }
    @media (max-width: 768px) {
        .cat-layout { grid-template-columns: 1fr; }
    }

    /* Form Card & Text Highlighting */
    .form-card {
        background: #1c1c1e;
        padding: 24px;
        border-radius: 8px;
        border: 1px solid #3f3f46;
        color: #f4f4f5;
    }
    .form-card h3 {
        color: #ffffff !important;
        margin-top: 0;
        margin-bottom: 16px;
    }
    .form-card label {
        color: #e4e4e7 !important;
        font-weight: 500;
    }

    /* Inputs & Buttons */
    .search-input {
        background-color: #27272a !important;
        color: #ffffff !important;
        border: 1px solid #52525b !important;
        padding: 10px 12px;
        border-radius: 6px;
        box-sizing: border-box;
    }
    .search-input::placeholder {
        color: #a1a1aa !important;
    }
    .btn {
        background-color: #0284c7;
        color: #ffffff !important;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn:hover {
        background-color: #0369a1;
    }

    /* Table Styling */
    .cat-table {
        width: 100%;
        border-collapse: collapse;
        background: #1c1c1e;
        border-radius: 8px;
        border: 1px solid #3f3f46;
        overflow: hidden;
    }
    .cat-table th {
        background: #27272a;
        color: #ffffff !important;
        font-weight: 600;
        padding: 14px 16px;
        text-align: left;
        border-bottom: 2px solid #3f3f46;
    }
    .cat-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid #27272a;
        color: #e4e4e7 !important;
    }
    .cat-table tr:hover {
        background-color: #27272a;
    }
    .cat-table td strong {
        color: #ffffff !important;
    }
    .cat-table a {
        color: #38bdf8 !important;
        font-weight: bold;
        text-decoration: none;
    }
    .cat-table a:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body>

    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a> | 
            <a href="manage_pages.php">📋 Manage Pages</a> | 
            <a href="create_page.php">+ Post Dish</a> | 
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <div style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
        <h2>Category Management</h2>

        <?php if ($error): ?>
            <p style="color: #ef4444; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: #22c55e; font-weight: bold;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <div class="cat-layout">
            <!-- Left: Add / Edit Form -->
            <div class="form-card">
                <h3><?= $edit_category ? 'Edit Category' : 'Add New Category' ?></h3>
                <form method="POST" action="manage_categories.php">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?= $edit_category['id'] ?>">
                    <?php endif; ?>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom: 6px;">Category Name:</label>
                        <input type="text" name="name" class="search-input" style="width: 100%;" required 
                               value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>" 
                               placeholder="e.g. Italian, Desserts, Vegan">
                    </div>

                    <button type="submit" class="btn" style="width: 100%;">
                        <?= $edit_category ? 'Update Category' : 'Create Category' ?>
                    </button>

                    <?php if ($edit_category): ?>
                        <a href="manage_categories.php" style="display: block; text-align: center; margin-top: 10px; color: #a1a1aa; text-decoration: none;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Right: Existing Categories List -->
            <div>
                <h3>Existing Categories</h3>
                <table class="cat-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Associated Dishes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <!-- Clickable Category Link that redirects to index.php filtered by this category -->
                                        <a href="index.php?category_id=<?= $cat['id'] ?>" title="View dishes in this category">
                                            <?= htmlspecialchars($cat['name']) ?> 🔍
                                        </a>
                                    </td>
                                    <td><?= $cat['total_posts'] ?> post(s)</td>
                                    <td>
                                        <a href="manage_categories.php?action=edit&id=<?= $cat['id'] ?>" style="color: #38bdf8;">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #a1a1aa;">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>