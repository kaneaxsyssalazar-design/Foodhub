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
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card-details {
            background: #1c1c1e;
            border-radius: 8px;
            border: 1px solid #27272a;
            padding: 28px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .section-box {
            background: #27272a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #3f3f46;
            margin-bottom: 25px;
        }

        .badge {
            background: #27272a;
            color: #e4e4e7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .badge-api {
            background: #854d0e;
            color: #fef08a;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #38bdf8;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div><strong>Food Hub Portal</strong></div>
        <div>
            <a href="index.php">🏠 Home</a>
        </div>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 20px; border-radius: 8px;">
                <h2>Error</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="index.php" class="btn-back" style="margin-top: 15px;">← Return to Home</a>
            </div>
        <?php elseif ($recipe): ?>
            <div class="card-details">
                
                <a href="index.php" class="btn-back">← Back to Home</a>

                <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 30px;">
                    <?php if (!empty($recipe['image'])): ?>
                        <a href="<?= htmlspecialchars($recipe['image']) ?>" data-lightbox="recipe-image" data-title="<?= htmlspecialchars($recipe['name']) ?>">
                            <img src="<?= htmlspecialchars($recipe['image']) ?>" alt="<?= htmlspecialchars($recipe['name']) ?>" style="width: 320px; height: 320px; object-fit: cover; border-radius: 8px; border: 1px solid #27272a; cursor: zoom-in;">
                        </a>
                    <?php endif; ?>
                    
                    <div style="flex: 1; min-width: 280px;">
                        <h1 style="font-size: 2rem; margin-bottom: 12px; color: #38bdf8;"><?= htmlspecialchars($recipe['name']) ?></h1>
                        
                        <p style="margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <span class="badge badge-api">API Recipe</span>
                            <span class="badge">Cuisine: <?= htmlspecialchars($recipe['cuisine'] ?? 'Global') ?></span>
                            <span class="badge">Difficulty: <?= htmlspecialchars($recipe['difficulty'] ?? 'N/A') ?></span>
                            <span class="badge">Prep Time: <?= htmlspecialchars($recipe['prepTimeMinutes'] ?? 0) ?> mins</span>
                        </p>

                        <?php if (!empty($recipe['tags'])): ?>
                            <p style="color: #a1a1aa; font-size: 0.9rem; margin-top: 10px;">
                                <strong>Tags:</strong> <?= htmlspecialchars(implode(', ', $recipe['tags'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ingredients List Section -->
                <div class="section-box">
                    <h2 style="margin-top: 0; margin-bottom: 12px; color: #38bdf8;">🥗 Ingredients</h2>
                    <ul style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding-left: 20px; color: #e4e4e7; margin: 0;">
                        <?php if (!empty($recipe['ingredients'])): ?>
                            <?php foreach ($recipe['ingredients'] as $ingredient): ?>
                                <li><?= htmlspecialchars($ingredient) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Step-by-Step Instructions -->
                <div class="section-box" style="margin-bottom: 0;">
                    <h2 style="margin-top: 0; margin-bottom: 12px; color: #38bdf8;">👨‍🍳 Instructions</h2>
                    <ol style="line-height: 1.8; color: #e4e4e7; padding-left: 20px; margin: 0;">
                        <?php if (!empty($recipe['instructions'])): ?>
                            <?php foreach ($recipe['instructions'] as $step): ?>
                                <li style="margin-bottom: 8px;"><?= htmlspecialchars($step) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>

            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox-plus-jquery.min.js"></script>
</body>
</html>