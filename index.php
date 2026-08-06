<?php
session_start();
require_once 'db_connect.php';

// Check admin status matching your session configuration
$is_admin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)
         || (isset($_SESSION['username']) && strtolower($_SESSION['username']) === 'admin')
         || (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1);

// Fetch categories for filter/navigation dropdown
$cat_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- SEARCH, SORT & PAGINATION PARAMETERS ---

// Items per page (N) - Adjust this number to test pagination
$items_per_page = 6; 

$search_keyword = trim(filter_input(INPUT_GET, 'q', FILTER_DEFAULT) ?? '');
$selected_category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($current_page < 1) {
    $current_page = 1;
}

// --- SORTING LOGIC (SECTION 7.4) ---
$sort_by = filter_input(INPUT_GET, 'sort', FILTER_DEFAULT) ?? 'created_at';
$sort_dir = strtoupper(filter_input(INPUT_GET, 'dir', FILTER_DEFAULT) ?? 'DESC');

// Whitelist allowed column names to prevent SQL Injection
$allowed_sort_columns = [
    'title'      => 'p.title',
    'created_at' => 'p.created_at',
    'updated_at' => 'p.updated_at'
];

$sort_column = $allowed_sort_columns[$sort_by] ?? 'p.created_at';
$sort_direction = ($sort_dir === 'ASC') ? 'ASC' : 'DESC';

// Build dynamic WHERE clause based on keyword and category inputs
$where_clauses = [];
$params = [];

if ($selected_category) {
    $where_clauses[] = "p.category_id = :cat_id";
    $params[':cat_id'] = $selected_category;
}

if (!empty($search_keyword)) {
    $where_clauses[] = "(p.title LIKE :keyword1 OR p.content LIKE :keyword2)";
    $params[':keyword1'] = "%{$search_keyword}%";
    $params[':keyword2'] = "%{$search_keyword}%";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// --- 1. COUNT TOTAL DATABASE RESULTS ---
$count_sql = "SELECT COUNT(*) FROM pages p {$where_sql}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_results = (int)$count_stmt->fetchColumn();

// Calculate total pages needed
$total_pages = ceil($total_results / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $items_per_page;

// --- 2. FETCH PAGINATED & SORTED COMMUNITY POSTS ---
$sql = "SELECT p.*, c.name AS category_name 
        FROM pages p 
        LEFT JOIN categories c ON p.category_id = c.id 
        {$where_sql} 
        ORDER BY {$sort_column} {$sort_direction} 
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. DYNAMIC API RECIPE FETCHING ---
// Fetch recipes with randomized offset (skip) so new recipes appear on refresh
$api_recipes = [];

// Only fetch API recipes when browsing page 1 without active category filters or keyword searches
if (empty($search_keyword) && !$selected_category && $current_page == 1) {
    $random_skip = rand(0, 40);
    $api_url = "https://dummyjson.com/recipes?limit=6&skip={$random_skip}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $api_response = curl_exec($ch);
    
    if ($api_response !== false) {
        $data = json_decode($api_response, true);
        $api_recipes = $data['recipes'] ?? [];
    }
    curl_close($ch);
}

// Helper function to keep search/filter/sort parameters intact in pagination links
function buildPaginationUrl($page_num) {
    $params = $_GET;
    $params['page'] = $page_num;
    return 'index.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Hub - Home</title>
    <link rel="stylesheet" href="style.css?v=5">
    <!-- Lightbox2 CSS for Image Zooming -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    <style>
        body {
            background-color: #121212;
            color: #f4f4f5;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Search Bar & Category Filter */
        .search-bar-container {
            background: #18181b;
            padding: 16px 20px;
            border-radius: 8px;
            border: 1px solid #27272a;
            margin-bottom: 25px;
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input, .search-select {
            background: #27272a;
            color: #fff;
            border: 1px solid #3f3f46;
            padding: 9px 12px;
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
        }

        .btn-search {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 9px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-search:hover {
            background: #0369a1;
        }

        .btn-reset {
            color: #a1a1aa;
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: 5px;
        }

        .section-header {
            color: #38bdf8;
            border-bottom: 1px solid #27272a;
            padding-bottom: 8px;
            margin-top: 35px;
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: #1c1c1e;
            border: 1px solid #27272a;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        /* Lightbox Hover Container Styling */
        .card-img-link {
            display: block;
            cursor: zoom-in;
            position: relative;
            overflow: hidden;
            background: #27272a;
            height: 180px;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease, opacity 0.2s ease;
        }

        .card-img-link:hover .card-img {
            opacity: 0.9;
            transform: scale(1.03);
        }

        .card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-title {
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            color: #38bdf8;
        }

        .badge-container {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .badge {
            background: #27272a;
            color: #e4e4e7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-api {
            background: #854d0e;
            color: #fef08a;
        }

        .card-preview {
            color: #a1a1aa;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .btn-view {
            display: inline-block;
            background: #0284c7;
            color: #ffffff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.9rem;
            text-align: center;
            align-self: flex-start;
        }

        .btn-view:hover {
            background: #0369a1;
        }

        /* Pagination Styling */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            background: #18181b;
            border: 1px solid #27272a;
            color: #38bdf8;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .pagination a:hover {
            background: #27272a;
        }

        .pagination .active-page {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .pagination .disabled {
            color: #52525b;
            border-color: #27272a;
            cursor: not-allowed;
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

        <!-- Combined Keyword, Category & Sorting Form -->
        <div class="search-bar-container">
            <form method="GET" action="index.php" class="search-form">
                <input 
                    type="text" 
                    name="q" 
                    class="search-input" 
                    placeholder="Search dishes by keyword..." 
                    value="<?= htmlspecialchars($search_keyword) ?>"
                >

                <select name="category" class="search-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $selected_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Section 7.4 Sort Selection Dropdown -->
                <select name="sort" class="search-select">
                    <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date Created</option>
                    <option value="title" <?= $sort_by === 'title' ? 'selected' : '' ?>>Title</option>
                    <option value="updated_at" <?= $sort_by === 'updated_at' ? 'selected' : '' ?>>Date Updated</option>
                </select>

                <select name="dir" class="search-select">
                    <option value="DESC" <?= $sort_direction === 'DESC' ? 'selected' : '' ?>>Descending (Newest / Z-A)</option>
                    <option value="ASC" <?= $sort_direction === 'ASC' ? 'selected' : '' ?>>Ascending (Oldest / A-Z)</option>
                </select>

                <button type="submit" class="btn-search">Search / Sort</button>

                <?php if (!empty($search_keyword) || $selected_category || $sort_by !== 'created_at' || $sort_dir !== 'DESC'): ?>
                    <a href="index.php" class="btn-reset">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Community Posts Grid -->
        <h2 class="section-header">Community Dishes</h2>
        <div style="color: #a1a1aa; font-size: 0.9rem; margin-bottom: 15px;">
            Showing <?= count($posts) ?> of <?= $total_results ?> posts
            <?php if (!empty($search_keyword)): ?>
                for "<strong><?= htmlspecialchars($search_keyword) ?></strong>"
            <?php endif; ?>
        </div>

        <div class="grid">
            <?php if (!empty($posts)): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="card">
                        <!-- LIGHTVIEW / LIGHTBOX WRAPPER -->
                        <?php if (!empty($post['image_path']) && file_exists($post['image_path'])): ?>
                            <a href="<?= htmlspecialchars($post['image_path']) ?>" data-lightbox="community-dishes" data-title="<?= htmlspecialchars($post['title']) ?>" class="card-img-link">
                                <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="card-img">
                            </a>
                        <?php else: ?>
                            <div style="height: 180px; background: #27272a; display: flex; align-items: center; justify-content: center; color: #71717a;">
                                No Image Available
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <h3 class="card-title"><?= htmlspecialchars($post['title']) ?></h3>
                            
                            <div class="badge-container">
                                <span class="badge"><?= htmlspecialchars($post['category_name'] ?? 'Uncategorized') ?></span>
                                <?php if (!empty($post['country_of_origin'])): ?>
                                    <span class="badge"><?= htmlspecialchars($post['country_of_origin']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="card-preview">
                                <?php 
                                    $plain_text = strip_tags(html_entity_decode($post['content'] ?? ''));
                                    $snippet    = substr($plain_text, 0, 120);
                                ?>
                                <?= htmlspecialchars($snippet) ?><?= strlen($plain_text) > 120 ? '...' : '' ?>
                            </div>

                            <a href="post.php?id=<?= $post['id'] ?>" class="btn-view">View Post →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; padding: 30px; text-align: center; background: #18181b; border-radius: 8px; border: 1px solid #27272a;">
                    <p style="color: #a1a1aa; margin: 0;">No matching posts found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination Links -->
        <?php if ($total_results > $items_per_page): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="<?= buildPaginationUrl($current_page - 1) ?>">« Previous</a>
                <?php else: ?>
                    <span class="disabled">« Previous</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === (int)$current_page): ?>
                        <span class="active-page"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?= buildPaginationUrl($current_page + 1) ?>">Next »</a>
                <?php else: ?>
                    <span class="disabled">Next »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- API Recipes Section -->
        <?php if (!empty($api_recipes)): ?>
            <h2 class="section-header">Featured API Recipes</h2>
            <div class="grid">
                <?php foreach ($api_recipes as $recipe): ?>
                    <div class="card">
                        <!-- LIGHTVIEW / LIGHTBOX WRAPPER FOR API IMAGES -->
                        <?php if (!empty($recipe['image'])): ?>
                            <a href="<?= htmlspecialchars($recipe['image']) ?>" data-lightbox="api-recipes" data-title="<?= htmlspecialchars($recipe['name']) ?>" class="card-img-link">
                                <img src="<?= htmlspecialchars($recipe['image']) ?>" alt="<?= htmlspecialchars($recipe['name']) ?>" class="card-img">
                            </a>
                        <?php endif; ?>

                        <div class="card-body">
                            <h3 class="card-title"><?= htmlspecialchars($recipe['name']) ?></h3>
                            <div class="badge-container">
                                <span class="badge badge-api">API Recipe</span>
                                <span class="badge"><?= htmlspecialchars($recipe['cuisine'] ?? 'Global') ?></span>
                            </div>
                            <div class="card-preview">
                                Difficulty: <?= htmlspecialchars($recipe['difficulty'] ?? 'Medium') ?><br>
                                Prep Time: <?= htmlspecialchars($recipe['prepTimeMinutes'] ?? 0) ?> mins
                            </div>
                            <a href="recipe_details.php?id=<?= $recipe['id'] ?>" class="btn-view">View Recipe →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Lightbox2 JavaScript for Lightview Image Overlay -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>
</body>
</html>