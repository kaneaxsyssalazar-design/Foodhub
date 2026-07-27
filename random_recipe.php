<?php
session_start();

// Fetch a random meal from TheMealDB public API
$api_url = 'https://www.themealdb.com/api/json/v1/1/random.php';

// Use file_get_contents to make the HTTP GET request
$response = @file_get_contents($api_url);

$meal = null;
if ($response !== false) {
    $data = json_decode($response, true);
    if (!empty($data['meals'][0])) {
        $meal = $data['meals'][0];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global Recipe Discovery - Food Hub</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        nav { background: #333; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        nav a { color: #fff; margin-right: 15px; text-decoration: none; font-weight: bold; }
        nav a:hover { text-decoration: underline; }
        .card { border: 1px solid #ddd; padding: 20px; max-width: 650px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .recipe-img { max-width: 100%; height: auto; border-radius: 6px; }
        .btn { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background-color: #218838; }
        .tag { background: #e9ecef; padding: 3px 8px; border-radius: 3px; font-size: 0.9em; }
    </style>
</head>
<body>

    <!-- Unified Header Navigation -->
    <nav>
        <a href="index.php">Home / All Dishes</a>
        <a href="random_recipe.php">Discover Global Recipe (API)</a>
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
            <a href="create_page.php">+ Post Dish</a>
            <a href="moderate_comments.php">Moderate Comments</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Admin Login</a>
        <?php endif; ?>
    </nav>

    <h2>Explore Global Recipes</h2>
    <p>This page connects to an external REST API (TheMealDB) to discover featured dishes from around the world.</p>

    <?php if ($meal): ?>
        <div class="card">
            <h3><?= htmlspecialchars($meal['strMeal']) ?></h3>
            <p>
                <span class="tag"><strong>Category:</strong> <?= htmlspecialchars($meal['strCategory']) ?></span> 
                <span class="tag"><strong>Origin:</strong> <?= htmlspecialchars($meal['strArea']) ?></span>
            </p>

            <img src="<?= htmlspecialchars($meal['strMealThumb']) ?>" alt="Dish Image" class="recipe-img"><br><br>

            <h4>Instructions</h4>
            <p><?= nl2br(htmlspecialchars($meal['strInstructions'])) ?></p>

            <?php if (!empty($meal['strYoutube'])): ?>
                <p>📺 <a href="<?= htmlspecialchars($meal['strYoutube']) ?>" target="_blank">Watch Video Tutorial on YouTube</a></p>
            <?php endif; ?>

            <br>
            <button class="btn" onclick="location.reload();">🔄 Fetch Another Recipe</button>
        </div>
    <?php else: ?>
        <p style="color: red;">Unable to reach the recipe server right now. Please try refreshing.</p>
    <?php endif; ?>

</body>
</html>