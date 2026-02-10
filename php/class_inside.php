<?php
$class = $_GET['class'] ?? 'zumba';

$classData = [
    'zumba' => [
        'title' => 'Zumba',
        'image' => '../pictures/zumba.jpg',
        'desc' => 'High-energy dance-based fitness program.',
        'trainer' => 'Ram Tamang',
        'location' => 'Hall A',
        'time' => '6:00 AM - 7:00 AM'
    ],
    'yoga' => [
        'title' => 'Yoga',
        'image' => '../pictures/yoga.jpg',
        'desc' => 'Balance, flexibility, and mindfulness.',
        'trainer' => 'Priya Gurung',
        'location' => 'Hall B',
        'time' => '7:30 AM - 8:30 AM'
    ],
    'boxing' => [
        'title' => 'Boxing',
        'image' => '../pictures/box.jpg',
        'desc' => 'Strength and conditioning boxing workout.',
        'trainer' => 'Sita Shrestha',
        'location' => 'Training Zone',
        'time' => '6:00 PM - 7:00 PM'
    ],
    'battle-ropes' => [
        'title' => 'Battle Ropes',
        'image' => '../pictures/battle-ropes.jpg',
        'desc' => 'Powerful rope intervals for full-body strength and conditioning.',
        'trainer' => 'Bikash Rana',
        'location' => 'Training Zone',
        'time' => '5:30 PM - 6:15 PM'
    ],
    'swimming' => [
        'title' => 'Swimming',
        'image' => '../pictures/swim.jpg',
        'desc' => 'Strength and conditioning swimming workout.',
        'trainer' => 'Hari Bhandari',
        'location' => 'Training Zone',
        'time' => '6:00 PM - 7:00 PM'
    ],
    'hiit' => [
        'title' => 'HIIT',
        'image' => '../pictures/battle-ropes.jpg',
        'desc' => 'High intensity interval training to burn fat and boost endurance.',
        'trainer' => 'Anil Sharma',
        'location' => 'Studio 1',
        'time' => '6:30 AM - 7:15 AM'
    ],
    'chair-yoga' => [
        'title' => 'Chair Yoga',
        'image' => '../pictures/yoga.jpg',
        'desc' => 'Gentle seated yoga for mobility and recovery.',
        'trainer' => 'Maya Thapa',
        'location' => 'Studio 2',
        'time' => '9:00 AM - 9:45 AM'
    ],
    'gentle-yoga' => [
        'title' => 'Gentle Yoga',
        'image' => '../pictures/yoga.jpg',
        'desc' => 'Slow-paced flow to improve flexibility and calm the body.',
        'trainer' => 'Priya Gurung',
        'location' => 'Studio 2',
        'time' => '8:00 AM - 9:00 AM'
    ],
    'stretch-mobility' => [
        'title' => 'Stretch & Mobility',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Restore range of motion with guided mobility work.',
        'trainer' => 'Ramesh Karki',
        'location' => 'Recovery Zone',
        'time' => '5:30 PM - 6:15 PM'
    ],
    'beginner-pilates' => [
        'title' => 'Beginner Pilates',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Core-focused training to improve posture and stability.',
        'trainer' => 'Sarina Rai',
        'location' => 'Studio 1',
        'time' => '7:00 AM - 7:45 AM'
    ],
    'power-yoga' => [
        'title' => 'Power Yoga',
        'image' => '../pictures/yoga.jpg',
        'desc' => 'Faster flow for strength, sweat, and focus.',
        'trainer' => 'Priya Gurung',
        'location' => 'Studio 2',
        'time' => '6:00 AM - 6:50 AM'
    ],
    'bodyweight-strength' => [
        'title' => 'Bodyweight Strength',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Full-body strength using only your bodyweight.',
        'trainer' => 'Bikash Rana',
        'location' => 'Training Zone',
        'time' => '5:00 PM - 5:45 PM'
    ],
    'core-conditioning' => [
        'title' => 'Core Conditioning',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Build a strong core with focused movements.',
        'trainer' => 'Sita Shrestha',
        'location' => 'Studio 1',
        'time' => '7:15 AM - 8:00 AM'
    ],
    'low-impact-cardio' => [
        'title' => 'Low-Impact Cardio',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Heart-healthy cardio with minimal joint stress.',
        'trainer' => 'Maya Thapa',
        'location' => 'Studio 2',
        'time' => '9:30 AM - 10:15 AM'
    ],
    'dance-fitness' => [
        'title' => 'Dance Fitness',
        'image' => '../pictures/zumba.jpg',
        'desc' => 'Rhythm-based cardio to burn calories and have fun.',
        'trainer' => 'Ram Tamang',
        'location' => 'Hall A',
        'time' => '5:30 PM - 6:30 PM'
    ],
    'functional-training' => [
        'title' => 'Functional Training',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Build real-world strength with compound movements.',
        'trainer' => 'Bikash Rana',
        'location' => 'Training Zone',
        'time' => '6:00 PM - 7:00 PM'
    ],
    'bootcamp' => [
        'title' => 'Bootcamp',
        'image' => '../pictures/battle-ropes.jpg',
        'desc' => 'High-energy mix of strength and cardio circuits.',
        'trainer' => 'Sita Shrestha',
        'location' => 'Outdoor Zone',
        'time' => '6:00 AM - 7:00 AM'
    ],
    'spin' => [
        'title' => 'Spin / Indoor Cycling',
        'image' => '../pictures/workout.jpg',
        'desc' => 'Fast-paced cycling to boost endurance.',
        'trainer' => 'Sarina Rai',
        'location' => 'Studio 3',
        'time' => '6:30 AM - 7:15 AM'
    ],
    'cardio-kickboxing' => [
        'title' => 'Cardio Kickboxing',
        'image' => '../pictures/box.jpg',
        'desc' => 'Cardio + martial arts combos for total-body fitness.',
        'trainer' => 'Sita Shrestha',
        'location' => 'Hall B',
        'time' => '7:00 PM - 8:00 PM'
    ],
];

$data = $classData[$class];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $data['title'] ?> | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="/fitgym/css/header.css">
    <link rel="stylesheet" href="/fitgym/css/footer.css">
    <link rel="stylesheet" href="/fitgym/css/class_inside.css">
</head>

<body>

<?php include "header.php"; ?>

<section class="class-detail">
    <div class="detail-container">

        <img src="<?= $data['image'] ?>" class="detail-img">

        <div class="detail-info">
            <h2><?= $data['title'] ?></h2>
            <p><?= $data['desc'] ?></p>

            <ul>
                <li><strong>Trainer:</strong> <?= $data['trainer'] ?></li>
                <li><strong>Time:</strong> <?= $data['time'] ?></li>
                <li><strong>Location:</strong> <?= $data['location'] ?></li>
            </ul>

            <a class="book-btn" href="book_class.php?class=<?= urlencode($class) ?>">Book Now</a>
        </div>

    </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>
