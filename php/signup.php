<?php
session_start();
include("db.php");

$error = "";
$success = "";
define('BASE_URL', '/fitgym');

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $phone    = trim($_POST["phone"]);
    $gender   = $_POST["gender"];

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, phone, gender)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssss",
            $name,
            $email,
            $hashedPassword,
            $phone,
            $gender
        );

        if ($stmt->execute()) {
            $success = "Account created successfully! You can login now.";
        } else {
            $error = "Something went wrong. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/pictures/favicon.png">


    <link rel="stylesheet" href="<?= BASE_URL ?>/css/signup.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/header.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/footer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/index.css">
</head>

<body>


<?php include "header.php"; ?>

<main class="page-content">
    <div class="signup-wrapper">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>

        <form method="POST" class="signup-form">
            <label>Name</label>
            <input type="text" name="name" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Phone</label>
            <input type="text" name="phone">

            <label>Gender</label>
            <select name="gender">
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>

            <button type="submit" class="signup-btn">Create Account</button>
        </form>

        <p class="login-text">
            Already have an account?
            <a href="login.php">Login</a>
        </p>
    </div>
</main>


<?php include "footer.php"; ?>

</body>
</html>
