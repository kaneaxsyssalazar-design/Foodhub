<?php
session_start();

$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$recipe = null;
$error = '';

if ($recipe_id && $recipe_id > 0) {
    // Fetch detailed recipe by ID directly from DummyJSON API
    $api_url = "https://dummyjson.com/recipes/{$recipe_id}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Prevents SSL failures on local setups

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = 'Network error: ' . curl_error($ch);
    } else if ($http_code === 200 && $response) {
        $recipe = json_decode($response, true);
        if (empty($recipe) || isset($recipe['message'])) {
            $error = 'Recipe not found.';
            $recipe = null;
        }
    } else {
        $error = 'Recipe not found.';
    }

    curl_close($ch);
} else {
    $error = 'Invalid recipe ID provided.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $recipe ? htmlspecialchars($recipe['name']) : 'Recipe Details' ?> - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a>
        </div>
    </header>

    <main style="max-width: 900px; margin: 20px auto; padding: 0 15px;">
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 20px; border-radius: var(--radius-md, 8px);">
                <h2>Error</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="index.php" class="btn" style="margin-top: 15px; display: inline-block;">← Return to Home</a>
            </div>
        <?php elseif ($recipe): ?>
            <div style="background: var(--bg-card, #1c1c1e); border-radius: var(--radius-lg, 8px); border: 1px solid var(--border-color, #27272a); padding: 28px; box-shadow: var(--shadow-sm);">
                
                <a href="index.php" class="btn btn-secondary" style="margin-bottom: 20px; display: inline-block; color: #38bdf8; text-decoration: none; font-weight: bold;">← Back to Home</a>

                <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 30px;">
                    <?php if (!empty($recipe['image'])): ?>
                        <a href="<?= htmlspecialchars($recipe['image']) ?>" data-lightbox="recipe-image" data-title="<?= htmlspecialchars($recipe['name']) ?>">
                            <img src="<?= htmlspecialchars($recipe['image']) ?>" alt="<?= htmlspecialchars($recipe['name']) ?>" style="width: 320px; height: 320px; object-fit: cover; border-radius: var(--radius-md, 8px); border: 1px solid var(--border-color, #27272a); cursor: zoom-in;">
                        </a>
                    <?php endif; ?>
                    
                    <div style="flex: 1; min-width: 280px;">
                        <h1 style="font-size: 2rem; margin-bottom: 12px; color: var(--text-main, #f4f4f5);"><?= htmlspecialchars($recipe['name']) ?></h1>
                        
                        <p style="margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <span class="badge" style="font-size: 0.9rem; background: #854d0e; color: #fef08a; padding: 4px 8px; border-radius: 4px; font-weight: bold;">API Recipe</span>
                            <span class="badge" style="font-size: 0.9rem; background: #27272a; color: #e4e4e7; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Cuisine: <?= htmlspecialchars($recipe['cuisine'] ?? 'Global') ?></span>
                            <span class="badge" style="font-size: 0.9rem; background: #27272a; color: #e4e4e7; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Difficulty: <?= htmlspecialchars($recipe['difficulty'] ?? 'N/A') ?></span>
                            <span class="badge" style="font-size: 0.9rem; background: #27272a; color: #e4e4e7; padding: 4px 8px; border-radius: 4px; font-weight: bold;">Prep Time: <?= htmlspecialchars($recipe['prepTimeMinutes'] ?? 0) ?> mins</span>
                        </p>

                        <?php if (!empty($recipe['tags'])): ?>
                            <p style="color: #a1a1aa; font-size: 0.9rem; margin-top: 10px;">
                                <strong>Tags:</strong> <?= htmlspecialchars(implode(', ', $recipe['tags'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ingredients List Section -->
                <div style="margin-bottom: 30px; background: var(--bg-secondary, rgba(255, 255, 255, 0.04)); padding: 20px; border-radius: var(--radius-md, 8px); border: 1px solid var(--border-color, #27272a);">
                    <h2 style="margin-bottom: 12px; color: #38bdf8;">🥗 Ingredients</h2>
                    <ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding-left: 20px; color: var(--text-main, #d4d4d8);">
                        <?php if (!empty($recipe['ingredients'])): ?>
                            <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                                <li><?= htmlspecialchars($ingredient) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Step-by-Step Instructions -->
                <div style="background: var(--bg-secondary, rgba(255, 255, 255, 0.04)); padding: 20px; border-radius: var(--radius-md, 8px); border: 1px solid var(--border-color, #27272a);">
                    <h2 style="margin-bottom: 12px; color: #38bdf8;">👨‍🍳 Instructions</h2>
                    <ol style="line-height: 1.8; color: var(--text-main, #d4d4d8); padding-left: 20px;">
                        <?php if (!empty($recipe['instructions'])): ?>
                            <?php foreach ($recipe['instructions'] as $step): ?>
                                <li style="margin-bottom: 8px;"><?= htmlspecialchars($step) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>

            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>
</body>
</html>