<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">


<?php
$current_page = basename($_SERVER["PHP_SELF"]);
?>

<header class="site-header">
    <div class="nav-container">

        <!-- LEFT LOGO + BRAND -->
        <div class="logo-section">
            <a href="/fitgym/index.php">
                <img src="/fitgym/pictures/favicon.png" alt="FitGym Logo" class="logo-img">
            </a>
            <h1 class="brand-title">FitGym</h1>
        </div>

        <!-- NAVIGATION -->
        <nav class="navbar">
            <ul class="nav-links">

                <li><a href="/fitgym/index.php"
                       class="<?= $current_page == 'index.php' ? 'active' : '' ?>">Home</a></li>

                <li><a href="/fitgym/php/classes.php"
                       class="<?= $current_page == 'classes.php' ? 'active' : '' ?>">Classes</a></li>

                <li><a href="/fitgym/php/contact.php"
                       class="<?= $current_page == 'contact.php' ? 'active' : '' ?>">Contact</a></li>

                <li><a href="/fitgym/php/about.php"
                       class="<?= $current_page == 'about.php' ? 'active' : '' ?>">About Us</a></li>

                <li>
                    <a href="/fitgym/php/login.php"
                       class="<?= $current_page == 'login.php' ? 'active' : '' ?>"
                       aria-label="Login" title="Login">
                        <img src="/fitgym/pictures/user.svg" alt="" width="30" height="30" class="icon" aria-hidden="true">
                        <span class="sr-only"></span>
                    </a>
                </li>

            </ul>
        </nav>

    </div>
</header>

<!-- DROPDOWN JS -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const toggle = document.getElementById("userToggle");
    const dropdown = document.getElementById("userDropdown");

    toggle.addEventListener("click", () => {
        dropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
        if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove("show");
        }
    });
});
</script>
