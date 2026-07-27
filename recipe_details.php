<?php
session_start();

$meal_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$meal = null;
$error = '';

if ($meal_id) {
    // Fetch detailed meal information by ID from TheMealDB API
    $api_url = "https://www.themealdb.com/api/json/v1/1/lookup.php?i={$meal_id}";
    $response = @file_get_contents($api_url);

    if ($response !== false) {
        $data = json_decode($response, true);
        if (!empty($data['meals'][0])) {
            $meal = $data['meals'][0];
        } else {
            $error = 'Recipe not found.';
        }
    } else {
        $error = 'Failed to connect to the external recipe API.';
    }
} else {
    $error = 'Invalid recipe ID provided.';
}

// Extract ingredients and measurements from API response dynamically
$ingredients = [];
if ($meal) {
    for ($i = 1; $i <= 20; $i++) {
        $ingredient = trim($meal["strIngredient{$i}"] ?? '');
        $measure = trim($meal["strMeasure{$i}"] ?? '');

        if (!empty($ingredient)) {
            $ingredients[] = [
                'name' => $ingredient,
                'measure' => $measure
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $meal ? htmlspecialchars($meal['strMeal']) : 'Recipe Details' ?> - Food Hub</title>
    <link rel="stylesheet" href="style.css?v=4">
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
        <?php elseif ($meal): ?>
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 28px; box-shadow: var(--shadow-sm);">
                
                <a href="index.php" class="btn btn-secondary" style="margin-bottom: 20px; display: inline-block;">← Back to Home</a>

                <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 30px;">
                    <img src="<?= htmlspecialchars($meal['strMealThumb']) ?>" alt="<?= htmlspecialchars($meal['strMeal']) ?>" style="width: 320px; height: 320px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    
                    <div style="flex: 1; min-width: 280px;">
                        <h1 style="font-size: 2rem; margin-bottom: 12px; color: var(--text-main);"><?= htmlspecialchars($meal['strMeal']) ?></h1>
                        
                        <p style="margin-bottom: 16px;">
                            <span class="badge" style="font-size: 0.9rem;">Category: <?= htmlspecialchars($meal['strCategory']) ?></span>
                            <span class="badge" style="font-size: 0.9rem;">Region: <?= htmlspecialchars($meal['strArea']) ?></span>
                        </p>

                        <?php if (!empty($meal['strYoutube'])): ?>
                            <p style="margin-top: 15px;">
                                <a href="<?= htmlspecialchars($meal['strYoutube']) ?>" target="_blank" class="btn" style="background-color: #dc2626; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px; display: inline-block; font-weight: bold;">
                                    ▶ Watch Video Tutorial on YouTube
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ingredients List Section -->
                <div style="margin-bottom: 30px; background: var(--bg-secondary, rgba(255, 255, 255, 0.04)); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <h2 style="margin-bottom: 12px; color: var(--text-main);">🥗 Ingredients</h2>
                    <ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding-left: 20px; color: var(--text-main);">
                        <?php foreach ($ingredients as $item): ?>
                            <li>
                                <strong><?= htmlspecialchars($item['measure']) ?></strong> <?= htmlspecialchars($item['name']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Step-by-Step Instructions -->
                <div>
                    <h2 style="margin-bottom: 12px; color: var(--text-main);">👨‍🍳 Instructions</h2>
                    <div style="line-height: 1.8; white-space: pre-line; color: var(--text-main);">
                        <?= htmlspecialchars($meal['strInstructions']) ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </main>

</body>
</html>