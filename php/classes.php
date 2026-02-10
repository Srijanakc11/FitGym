<?php
session_start();
include "database.php";

/* -------------------------------------------------
   CLASS DATA (LATER CAN COME FROM DB)
-------------------------------------------------- */
$classes = [
    [   'slug' => 'zumba',
        'title' => 'Zumba',
        'category' => 'Aerobics',
        'image' => '../pictures/zumba.jpg',
        'description' => 'High-energy dance workout to improve cardio and coordination.'
    ],
    [   'slug' => 'yoga',
        'title' => 'Yoga',
        'category' => 'Group Class',
        'image' => '../pictures/yoga.jpg',
        'description' => 'Mindful stretching and breathing for flexibility and balance.'
    ],
    [   'slug' => 'battle-ropes',
        'title' => 'Battle Ropes',
        'category' => 'Solo Class',
        'image' => '../pictures/battle-ropes.jpg',
        'description' => 'Powerful, short-interval training for strength and conditioning.'
    ],
    [   'slug' => 'boxing',
        'title' => 'Boxing',
        'category' => 'Strength',
        'image' => '../pictures/box.jpg',
        'description' => 'Technique and conditioning with focus pads and bags.'
    ],
    [
        'slug' => 'hiit',
        'title' => 'HIIT',
        'category' => 'Aerobics',
        'image' => '../pictures/battle-ropes.jpg',
        'description' => 'High intensity intervals for fat loss and stamina.'
    ],
    [
        'slug' => 'swimming',
        'title' => 'Swimming',
        'category' => 'Aerobics',
        'image' => '../pictures/swim.jpg',
        'description' => 'Low-impact full-body cardio in the pool.'
    ],
    [
        'slug' => 'chair-yoga',
        'title' => 'Chair Yoga',
        'category' => 'Group Class',
        'image' => '../pictures/yoga.jpg',
        'description' => 'Gentle seated yoga for mobility and recovery.'
    ],
    [
        'slug' => 'gentle-yoga',
        'title' => 'Gentle Yoga',
        'category' => 'Group Class',
        'image' => '../pictures/yoga.jpg',
        'description' => 'Slow-paced flow to improve flexibility and calm the body.'
    ],
    [
        'slug' => 'stretch-mobility',
        'title' => 'Stretch & Mobility',
        'category' => 'Solo Class',
        'image' => '../pictures/workout.jpg',
        'description' => 'Restore range of motion with guided mobility work.'
    ],
    [
        'slug' => 'beginner-pilates',
        'title' => 'Beginner Pilates',
        'category' => 'Group Class',
        'image' => '../pictures/workout.jpg',
        'description' => 'Core-focused training to improve posture and stability.'
    ],
    [
        'slug' => 'power-yoga',
        'title' => 'Power Yoga',
        'category' => 'Group Class',
        'image' => '../pictures/yoga.jpg',
        'description' => 'Faster flow for strength, sweat, and focus.'
    ],
    [
        'slug' => 'bodyweight-strength',
        'title' => 'Bodyweight Strength',
        'category' => 'Strength',
        'image' => '../pictures/workout.jpg',
        'description' => 'Full-body strength using only your bodyweight.'
    ],
    [
        'slug' => 'core-conditioning',
        'title' => 'Core Conditioning',
        'category' => 'Strength',
        'image' => '../pictures/workout.jpg',
        'description' => 'Build a strong core with focused movements.'
    ],
    [
        'slug' => 'low-impact-cardio',
        'title' => 'Low-Impact Cardio',
        'category' => 'Aerobics',
        'image' => '../pictures/workout.jpg',
        'description' => 'Heart-healthy cardio with minimal joint stress.'
    ],
    [
        'slug' => 'dance-fitness',
        'title' => 'Dance Fitness',
        'category' => 'Aerobics',
        'image' => '../pictures/zumba.jpg',
        'description' => 'Rhythm-based cardio to burn calories and have fun.'
    ],
    [
        'slug' => 'functional-training',
        'title' => 'Functional Training',
        'category' => 'Strength',
        'image' => '../pictures/workout.jpg',
        'description' => 'Build real-world strength with compound movements.'
    ],
    [
        'slug' => 'bootcamp',
        'title' => 'Bootcamp',
        'category' => 'Strength',
        'image' => '../pictures/battle-ropes.jpg',
        'description' => 'High-energy mix of strength and cardio circuits.'
    ],
    [
        'slug' => 'spin',
        'title' => 'Spin / Indoor Cycling',
        'category' => 'Aerobics',
        'image' => '../pictures/workout.jpg',
        'description' => 'Fast-paced cycling to boost endurance.'
    ],
    [
        'slug' => 'cardio-kickboxing',
        'title' => 'Cardio Kickboxing',
        'category' => 'Aerobics',
        'image' => '../pictures/box.jpg',
        'description' => 'Cardio + martial arts combos for total-body fitness.'
    ],
];

// GET search and filter inputs
$searchQuery = trim($_GET['q'] ?? '');
$filterCategory = $_GET['filter'] ?? 'all';

/* -------------------------------------------------
   CLASS FILTER FUNCTION
-------------------------------------------------- */
function class_matches($search, $filter, $item) {
    $search = mb_strtolower($search);
    $filter = mb_strtolower($filter);

    $title = mb_strtolower($item['title']);
    $category = mb_strtolower($item['category']);
    $description = mb_strtolower($item['description']);

    if ($filter !== 'all' && $filter !== '' && mb_strtolower($filter) !== $category) {
        return false;
    }

    if ($search === '') return true;

    return (
        mb_stripos($title, $search) !== false ||
        mb_stripos($category, $search) !== false ||
        mb_stripos($description, $search) !== false
    );
}

// Apply filtering
$visibleClasses = array_values(
    array_filter($classes, fn($c) => class_matches($searchQuery, $filterCategory, $c))
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FitGym — Classes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="/fitgym/pictures/favicon.png">

    <!-- GLOBAL -->
    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">

    <!-- PAGE -->
    <link rel="stylesheet" href="/fitgym/css/classes.css">

    <link rel="stylesheet" href="../css/index.css">
</head>


<body>

    <!-- HEADER INCLUDE -->
    <?php include "header.php"; ?>

    <main class="content">
        
        <section class="page-header">
            <h2>Our Fitness Classes</h2>
            <p class="lead">Choose from multiple programs designed for strength, cardio, flexibility, weight loss and recovery.</p>
        </section>

        <!-- RECOMMENDATIONS TEASER -->
        <section class="recommend-teaser simple">
            <div class="container">
                <h2>Smart Class Recommendations</h2>
                <p>Get quick TDEE-based suggestions for class intensity and weekly frequency.</p>
                <a href="/fitgym/php/recommend.php" class="btn">Get Recommendations</a>
            </div>
        </section>

        <!-- SEARCH + FILTER -->
        <section class="class-controls">
            <div class="container">
            <form class="search-form" method="GET" action="">

                <select class="activitiesDropdownList" id="activitiesDropdownList" name="filter" class="filter-dropdown">
                <option value="all" <?= strcasecmp($filterCategory, 'all') === 0 ? 'selected' : '' ?>>All Activities</option>
                <option value="Aerobics" <?= strcasecmp($filterCategory, 'Aerobics') === 0 ? 'selected' : '' ?>>Aerobics</option>
                <option value="Strength" <?= strcasecmp($filterCategory, 'Strength') === 0 ? 'selected' : '' ?>>Strength</option>
                <option value="Group Class" <?= strcasecmp($filterCategory, 'Group Class') === 0 ? 'selected' : '' ?>>Group Class</option>
                <option value="Solo Class" <?= strcasecmp($filterCategory, 'Solo Class') === 0 ? 'selected' : '' ?>>Solo Class</option>
                </select>

                <input type="text" id="searchInput" class="searchInput" name="q"
                   value="<?= htmlspecialchars($searchQuery) ?>"
                   placeholder="Search class by name, category or keyword...">

                <button type="submit" class="btn">Search</button>
                <button type="button" class="btn secondary-btn" onclick="location.href='classes.php'">Reset</button>
            </form>
            </div>
        </section>

        <style>
            .class-controls {
                margin-top: 15px;
            }

            #activitiesDropdownList,
            #searchInput,
            .btn {
            padding: 10px 15px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            }

            #activitiesDropdownList {
            background-color: #fff;
            cursor: pointer;
            }

            #searchInput {
            flex: 1;
            min-width: 200px;
            }

            #searchInput:focus,
            #activitiesDropdownList:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
            }

            .btn {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            }

            .btn:hover {
            background-color: #0056b3;
            }

            .secondary-btn {
            background-color: #6c757d;
            }

            .secondary-btn:hover {
            background-color: #5a6268;
            }

            .class-card {
                max-width: 300px; /* Set a maximum width for class cards */
                margin-right: auto; /* Center the card */
            }
        </style>

        <!-- CLASS GRID -->
        <!-- <section class="classes-section">
            <div class="container class-grid" id="sport-cards">

                <?php if (empty($visibleClasses)): ?>
                    <div class="no-results">No classes match your search.</div>
                <?php else: ?>
                    <?php foreach ($visibleClasses as $c): ?>
                        <article class="class-card"
                                 data-title="<?= strtolower($c['title']) ?>"
                                 data-category="<?= strtolower($c['category']) ?>"
                                 data-description="<?= strtolower($c['description']) ?>">

                            <img src="<?= $c['image'] ?>" alt="<?= htmlspecialchars($c['title']) ?>">

                            <div class="card-content">
                                <h3><?= htmlspecialchars($c['title']) ?></h3>
                                <p class="category">Category: <?= htmlspecialchars($c['category']) ?></p>
                                <p class="desc"><?= htmlspecialchars($c['description']) ?></p>
                            </div>

                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section> -->

        <section class="classes-section">
    <div class="class-grid">

        <?php if (empty($visibleClasses)): ?>
            <div class="no-results">No classes match your search.</div>
        <?php else: ?>
            <?php foreach ($visibleClasses as $c): ?>
            <a href="class_inside.php?class=<?= $c['slug'] ?? '' ?>" class="class-card">
                <img src="<?= $c['image'] ?>" alt="<?= htmlspecialchars($c['title']) ?>">
                <div class="card-content">
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="category"><?= htmlspecialchars($c['category']) ?></p>
                    <p class="desc"><?= htmlspecialchars($c['description']) ?></p>
                    <span class="view-btn">View Details</span>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</section>
    </main>

    <!-- FOOTER INCLUDE -->
    <?php include "footer.php"; ?>

    <script src="../js/classes.js"></script>

</body>
</html>
