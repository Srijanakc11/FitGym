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
    <div class="signup-buffer"></div>

    <div class="signup-wrapper">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="signup-form">
            <div class="input-group">
                <label>Name</label>
                <input type="text" name="name" placeholder="John Doe" required>
            </div>

            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="example@fitgym.com" required>
            </div>

            <div class="input-group password-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <span class="toggle-password" onclick="togglePassword()">
                        <!-- Eye Icon (Open) -->
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="input-group">
                <label>Phone</label>
                <input type="text" name="phone" placeholder="+1 234 567 890">
            </div>

            <div class="input-group">
                <label>Gender</label>
                <select name="gender">
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <button type="submit" class="signup-btn">Create Account</button>
        </form>

        <p class="login-text">
            Already have an account?
            <a href="login.php">Login</a>
        </p>
    </div>
</main>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        // Switch to Eye Off (Crossed out)
        eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
    } else {
        passwordInput.type = 'password';
        // Switch back to Eye Open
        eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
    }
}
</script>


<?php include "footer.php"; ?>

</body>
</html>
