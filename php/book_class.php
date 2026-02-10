<?php
session_start();
require_once "database.php";

$class = $_GET['class'] ?? ($_POST['class_type'] ?? 'zumba');

$classData = [
    'zumba' => [
        'title' => 'Zumba',
        'image' => '../pictures/zumba.jpg',
        'trainer' => 'Ram Tamang',
        'location' => 'Hall A',
        'duration' => '60 mins',
        'price' => 'NPR 2000',
    ],
    'yoga' => [
        'title' => 'Yoga',
        'image' => '../pictures/yoga.jpg',
        'trainer' => 'Priya Gurung',
        'location' => 'Hall B',
        'duration' => '60 mins',
        'price' => 'NPR 1800',
    ],
    'boxing' => [
        'title' => 'Boxing',
        'image' => '../pictures/box.jpg',
        'trainer' => 'Sita Shrestha',
        'location' => 'Training Zone',
        'duration' => '60 mins',
        'price' => 'NPR 2500',
    ],
    'swimming' => [
        'title' => 'Swimming',
        'image' => '../pictures/swim.jpg',
        'trainer' => 'Hari Bhandari',
        'location' => 'Pool Deck',
        'duration' => '45 mins',
        'price' => 'NPR 2500',
    ],
    'hiit' => [
        'title' => 'HIIT',
        'image' => '../pictures/battle-ropes.jpg',
        'trainer' => 'Anil Sharma',
        'location' => 'Studio 1',
        'duration' => '45 mins',
        'price' => 'NPR 2200',
    ],
    'chair-yoga' => [
        'title' => 'Chair Yoga',
        'image' => '../pictures/yoga.jpg',
        'trainer' => 'Maya Thapa',
        'location' => 'Studio 2',
        'duration' => '45 mins',
        'price' => 'NPR 1600',
    ],
    'gentle-yoga' => [
        'title' => 'Gentle Yoga',
        'image' => '../pictures/yoga.jpg',
        'trainer' => 'Priya Gurung',
        'location' => 'Studio 2',
        'duration' => '60 mins',
        'price' => 'NPR 1800',
    ],
    'stretch-mobility' => [
        'title' => 'Stretch & Mobility',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Ramesh Karki',
        'location' => 'Recovery Zone',
        'duration' => '45 mins',
        'price' => 'NPR 1500',
    ],
    'beginner-pilates' => [
        'title' => 'Beginner Pilates',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Sarina Rai',
        'location' => 'Studio 1',
        'duration' => '45 mins',
        'price' => 'NPR 1700',
    ],
    'power-yoga' => [
        'title' => 'Power Yoga',
        'image' => '../pictures/yoga.jpg',
        'trainer' => 'Priya Gurung',
        'location' => 'Studio 2',
        'duration' => '50 mins',
        'price' => 'NPR 1900',
    ],
    'bodyweight-strength' => [
        'title' => 'Bodyweight Strength',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Bikash Rana',
        'location' => 'Training Zone',
        'duration' => '50 mins',
        'price' => 'NPR 2000',
    ],
    'core-conditioning' => [
        'title' => 'Core Conditioning',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Sita Shrestha',
        'location' => 'Studio 1',
        'duration' => '45 mins',
        'price' => 'NPR 1800',
    ],
    'low-impact-cardio' => [
        'title' => 'Low-Impact Cardio',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Maya Thapa',
        'location' => 'Studio 2',
        'duration' => '45 mins',
        'price' => 'NPR 1800',
    ],
    'dance-fitness' => [
        'title' => 'Dance Fitness',
        'image' => '../pictures/zumba.jpg',
        'trainer' => 'Ram Tamang',
        'location' => 'Hall A',
        'duration' => '60 mins',
        'price' => 'NPR 2000',
    ],
    'functional-training' => [
        'title' => 'Functional Training',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Bikash Rana',
        'location' => 'Training Zone',
        'duration' => '60 mins',
        'price' => 'NPR 2200',
    ],
    'bootcamp' => [
        'title' => 'Bootcamp',
        'image' => '../pictures/battle-ropes.jpg',
        'trainer' => 'Sita Shrestha',
        'location' => 'Outdoor Zone',
        'duration' => '60 mins',
        'price' => 'NPR 2300',
    ],
    'spin' => [
        'title' => 'Spin / Indoor Cycling',
        'image' => '../pictures/workout.jpg',
        'trainer' => 'Sarina Rai',
        'location' => 'Studio 3',
        'duration' => '45 mins',
        'price' => 'NPR 2100',
    ],
    'cardio-kickboxing' => [
        'title' => 'Cardio Kickboxing',
        'image' => '../pictures/box.jpg',
        'trainer' => 'Sita Shrestha',
        'location' => 'Hall B',
        'duration' => '60 mins',
        'price' => 'NPR 2400',
    ],
];

$timeSlots = [
    '6:00-7:00 AM',
    '7:00-8:00 AM',
    '8:00-9:00 AM',
    '9:00-10:00 AM',
    '12:00-1:00 PM',
    '4:00-5:00 PM',
    '5:00-6:00 PM',
    '6:00-7:00 PM',
    '7:00-8:00 PM'
];

$data = $classData[$class] ?? $classData['zumba'];

$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? 'guest@fitgym.com';

$errors = [];
$success = false;

$fullName = trim($_POST['full_name'] ?? $userName);
$email = trim($_POST['email'] ?? $userEmail);
$email = preg_replace('/[\p{Z}\s\x00-\x1F\x7F]+/u', '', $email);
$contactNumber = trim($_POST['contact_number'] ?? '');
$preferredDate = trim($_POST['preferred_date'] ?? '');
$timeSlot = trim($_POST['time_slot'] ?? '');
$classType = trim($_POST['class_type'] ?? $class);
$participants = (int)($_POST['participants'] ?? 1);
$paymentMethod = $_POST['payment'] ?? 'cash';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($db_error) || !$conn) {
        $errors[] = 'Database is not connected. Please check your database setup.';
    }
    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Please enter a valid full name (max 100 characters).';
    }

    $emailPattern = '/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/';
    if ($email === '') {
        $email = 'guest@fitgym.com';
    }

    if ($contactNumber === '' || !preg_match('/^[0-9+\\-\\s]{7,20}$/', $contactNumber)) {
        $errors[] = 'Please enter a valid contact number.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
    $dateErrors = DateTime::getLastErrors();
    if ($preferredDate === '' || !$dateObj || $dateErrors['warning_count'] || $dateErrors['error_count']) {
        $errors[] = 'Please select a valid date.';
    } else {
        $today = new DateTime('today');
        if ($dateObj < $today) {
            $errors[] = 'Preferred date must be today or later.';
        }
    }

    if (!in_array($timeSlot, $timeSlots, true)) {
        $errors[] = 'Please select a valid time slot.';
    }

    if (!array_key_exists($classType, $classData)) {
        $errors[] = 'Please select a valid class type.';
    }

    if ($participants < 1 || $participants > 20) {
        $errors[] = 'Participants must be between 1 and 20.';
    }

    if (!in_array($paymentMethod, ['cash', 'online'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if (empty($errors)) {
        $selectedClass = $classData[$classType];

        $stmt = $conn->prepare(
            "INSERT INTO bookings
            (class_slug, class_name, trainer_name, full_name, email, contact_number, preferred_date, time_slot, participants, payment_method, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->bind_param(
            "ssssssssis",
            $classType,
            $selectedClass['title'],
            $selectedClass['trainer'],
            $fullName,
            $email,
            $contactNumber,
            $preferredDate,
            $timeSlot,
            $participants,
            $paymentMethod
        );

        if ($stmt->execute()) {
            $success = true;
            $class = $classType;
            $data = $selectedClass;

            $_SESSION['last_booking'] = [
                'class_slug' => $classType,
                'class_name' => $selectedClass['title'],
                'trainer_name' => $selectedClass['trainer'],
                'preferred_date' => $preferredDate,
                'time_slot' => $timeSlot,
                'participants' => $participants,
                'price' => $selectedClass['price'],
                'payment_method' => $paymentMethod,
            ];

            if ($paymentMethod === 'online') {
                header('Location: /fitgym/php/payment_esewa.php');
                exit;
            }
        } else {
            $errors[] = 'Booking could not be saved. Please try again.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book <?= htmlspecialchars($data['title']) ?> | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="/fitgym/pictures/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/fitgym/css/footer.css">
    <link rel="stylesheet" href="/fitgym/css/index.css">
    <link rel="stylesheet" href="/fitgym/css/booking.css">
</head>
<body>

<header class="booking-header">
    <div class="booking-nav">
        <div class="brand">
            <a href="/fitgym/index.php" class="brand-link">
                <img src="../pictures/favicon.png" alt="FitGym Logo">
                <span>FitGym</span>
            </a>
        </div>
        <nav class="booking-menu" aria-label="Primary">
            <a href="/fitgym/index.php">Home</a>
            <a href="/fitgym/php/classes.php">Classes</a>
            <a href="#" aria-disabled="true">My Bookings</a>
            <a href="#" aria-disabled="true">Profile</a>
        </nav>
    </div>
</header>

<main class="booking-page">
    <section class="booking-hero">
        <div class="hero-content">
            <p class="eyebrow">Ready to train</p>
            <h1>Book Your Class</h1>
            <p class="subtext">Lock in your spot, choose a time, and get ready to move.</p>
        </div>
    </section>

    <section class="booking-wrapper">
        <div class="booking-grid">
            <article class="class-summary-card">
                <div class="summary-media">
                    <img src="<?= htmlspecialchars($data['image']) ?>" alt="<?= htmlspecialchars($data['title']) ?>">
                    <span class="badge">Featured</span>
                </div>
                <div class="summary-body">
                    <h2><?= htmlspecialchars($data['title']) ?></h2>
                    <p class="summary-line"><span>Trainer</span><?= htmlspecialchars($data['trainer']) ?></p>
                    <p class="summary-line"><span>Duration</span><?= htmlspecialchars($data['duration']) ?></p>
                    <p class="summary-line"><span>Location</span><?= htmlspecialchars($data['location']) ?></p>
                    <div class="price-tag">
                        <span>Class Price</span>
                        <strong><?= htmlspecialchars($data['price']) ?></strong>
                    </div>
                </div>
            </article>

            <div class="booking-card">
                <form id="bookingForm" class="booking-form" method="POST" action="">
                    <h3>Booking Details</h3>

                    <div id="formErrors" class="form-errors <?= !empty($errors) ? 'show' : '' ?>" role="alert">
                        <?php if (!empty($errors)): ?>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="field-group">
                        <label for="fullName">Full Name</label>
                        <input id="fullName" name="full_name" type="text" value="<?= htmlspecialchars($fullName) ?>" required>
                    </div>

                    <div class="field-group">
                        <label for="contactNumber">Contact Number</label>
                        <input id="contactNumber" name="contact_number" type="tel" value="<?= htmlspecialchars($contactNumber) ?>" placeholder="9800000000" required>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="classDate">
                                <span class="icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h2v2h6V2h2v2h3a2 2 0 0 1 2 2v13a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V6a2 2 0 0 1 2-2h3V2zm12 8H5v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9zM5 6v2h14V6H5z"/></svg>
                                </span>
                                Select Date
                            </label>
                            <input id="classDate" name="preferred_date" type="date" value="<?= htmlspecialchars($preferredDate) ?>" required>
                        </div>

                        <div class="field-group">
                            <label for="timeSlot">
                                <span class="icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20zm1 5h-2v6l4.25 2.55 1-1.65-3.25-1.9V7z"/></svg>
                                </span>
                                Select Time Slot
                            </label>
                            <select id="timeSlot" name="time_slot" required>
                                <option value="">Choose a time</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?= htmlspecialchars($slot) ?>" <?= $timeSlot === $slot ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(str_replace('-', '–', $slot)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="classType">Class Type</label>
                        <select id="classType" name="class_type" required>
                            <?php foreach ($classData as $slug => $classItem): ?>
                                <option value="<?= htmlspecialchars($slug) ?>" <?= $classType === $slug ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classItem['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="participants">Number of Participants</label>
                        <input id="participants" name="participants" type="number" min="1" max="20" value="<?= htmlspecialchars((string)$participants) ?>">
                    </div>

                    <div class="payment-section">
                        <h3>Payment</h3>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment" value="cash" <?= $paymentMethod === 'cash' ? 'checked' : '' ?>>
                                <span class="payment-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zm2 4h4v4H5v-4zm6 0h8v4h-8v-4z"/></svg>
                                </span>
                                Cash on Service
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment" value="online" <?= $paymentMethod === 'online' ? 'checked' : '' ?>>
                                <span class="payment-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm0 4v8h16V9H4zm2 2h6v2H6v-2z"/></svg>
                                </span>
                                Online Payment (Demo)
                            </label>
                        </div>
                        <div class="price-row">
                            <span>Class Price</span>
                            <strong><?= htmlspecialchars($data['price']) ?></strong>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="primary-btn">Confirm Booking</button>
                        <a class="secondary-btn" href="classes.php">Cancel</a>
                    </div>

                    <p id="successMessage" class="success-message <?= $success ? 'show' : '' ?>" role="status" aria-live="polite">
                        Your <?= htmlspecialchars($data['title']) ?> class has been successfully booked!
                    </p>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>

<script>
    const bookingForm = document.getElementById('bookingForm');
    const errorBox = document.getElementById('formErrors');

    bookingForm.addEventListener('submit', (event) => {
        const errors = [];

        const fullName = document.getElementById('fullName');
        const email = null;
        const contactNumber = document.getElementById('contactNumber');
        const preferredDate = document.getElementById('classDate');
        const timeSlot = document.getElementById('timeSlot');
        const classType = document.getElementById('classType');
        const participants = document.getElementById('participants');

        [fullName, contactNumber, preferredDate, timeSlot, classType, participants]
            .forEach((field) => field.classList.remove('field-error'));

        if (!fullName.value.trim()) {
            errors.push('Full name is required.');
            fullName.classList.add('field-error');
        }

        const phonePattern = /^[0-9+\\-\\s]{7,20}$/;
        if (!phonePattern.test(contactNumber.value.trim())) {
            errors.push('A valid contact number is required.');
            contactNumber.classList.add('field-error');
        }

        if (!preferredDate.value) {
            errors.push('Please select a preferred date.');
            preferredDate.classList.add('field-error');
        }

        if (!timeSlot.value) {
            errors.push('Please select a time slot.');
            timeSlot.classList.add('field-error');
        }

        if (!classType.value) {
            errors.push('Please select a class type.');
            classType.classList.add('field-error');
        }

        if (parseInt(participants.value, 10) < 1 || parseInt(participants.value, 10) > 20) {
            errors.push('Participants must be between 1 and 20.');
            participants.classList.add('field-error');
        }

        if (errors.length > 0) {
            event.preventDefault();
            errorBox.innerHTML = `<ul>${errors.map((error) => `<li>${error}</li>`).join('')}</ul>`;
            errorBox.classList.add('show');
        } else {
            errorBox.innerHTML = '';
            errorBox.classList.remove('show');
        }
    });
</script>
</body>
</html>
