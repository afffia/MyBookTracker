<?php
session_start();
require 'config.php';
if (!isset($_SESSION['name'])) {
    header("Location: user_page.php");
    exit();
}

$name = $_SESSION['name'];
$email = $_SESSION['email'];
$alerts = $_SESSION['alerts'] ?? [];


unset($_SESSION['alerts']);
$name = $_SESSION['name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';
if($name !== null) $_SESSION['name']=$name;


$user_email = $_SESSION['email'];
$user_query = $conn->query("SELECT reading_goal FROM users WHERE email = '$user_email'");
$user_data = $user_query->fetch_assoc();
$goal = $user_data['reading_goal'] ?? 0;

$stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$user_role = $stmt->get_result()->fetch_assoc()['role'];
$stmt->close();

if (!in_array($user_role, ['admin', 'moderator'])) {
    header("Location: admin_page.php"); exit();
}

$is_admin     = $user_role === 'admin';
$is_moderator = $user_role === 'moderator';

$count_query = $conn->query("SELECT COUNT(*) as total FROM user_books WHERE user_email = '$user_email' AND status = 'completed'");
$count_data = $count_query->fetch_assoc();
$current_progress = $count_data['total'];


$percent = ($goal > 0) ? min(100, round(($current_progress / $goal) * 100)) : 0;


$added_query = $conn->prepare("SELECT COUNT(*) as total FROM user_books WHERE user_email = ?");
$added_query->bind_param("s", $user_email);
$added_query->execute();
$total_added = $added_query->get_result()->fetch_assoc()['total'];


$read_query = $conn->prepare("SELECT COUNT(*) as total FROM user_books WHERE user_email = ? AND status = 'completed'");
$read_query->bind_param("s", $user_email);
$read_query->execute();
$total_read = $read_query->get_result()->fetch_assoc()['total'];
$review_query = $conn->prepare("SELECT COUNT(*) as total FROM user_books WHERE user_email = ? AND review IS NOT NULL AND review != ''");
$review_query->bind_param("s", $user_email);
$review_query->execute();
$total_reviews = $review_query->get_result()->fetch_assoc()['total'];


$rating_query = $conn->prepare("SELECT COUNT(*) as total FROM user_books WHERE user_email = ? AND rating IS NOT NULL");
$rating_query->bind_param("s", $user_email);
$rating_query->execute();
$total_rated = $rating_query->get_result()->fetch_assoc()['total'];
$rating_query->close();

?>
<!DOCTYPE html>
<html lang="en">
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
<head>
        <title>MBT_homepage</title>
    </head>
    <body><header>
        <h1 class="logo"> <a href="user_page.html">MyBookTracker</a> </h1>
           
        <div class="user">
            <?php if (!empty($name)): ?>
        <div class="profile-box">
                    <div class="avatar-circle"><?= strtoupper($name[0]) ?></div> 
                   <div class="drop-down">
    <?php if (in_array($user_role, ['admin', 'moderator'])): ?>
    <a href="admin_page.php">Admin Panel</a>
    <?php endif; ?>
    <a href="profile.php?email=<?= urlencode($user_email) ?>">My Profile</a>
    <a href="following.php">Following</a>
    <a href="user_page.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</div>
                </div>
                <?php else: ?>
                 <button class="btnloginpopup" name="login_btn">Login</button>
                 <?php endif; ?>
             </div></header>
<nav class="navbar"><ul>
<li><form action="search.php" method="GET">
                <div class="search">
                    <input class="search-input" type="search" name="q" 
                        placeholder="Search Books..." 
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        autocomplete="off">
                </div>
            </form></li>
            <?php if ($name): ?>
        <li><a href="add_book.php">Can't find what you're looking for?</a></li>
        <li><a href="category.php">Explore</a></li>
        <?php endif; ?>
        </ul>
</nav>
 <?php if (!empty($alerts)): ?>
<div class="alert-box" >
    <?php foreach ($alerts as $alert): ?>
    <div class="alert <?= $alert['type']; ?>"><p><?= $alert ['message']; ?></p>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<section><h1 style="text-align: center; ">Welcome, <?= $name ?? 'Reader'?>!</h1></section>
<div class="page-background">
 <div class="center-box"> 
        <h2><?= $name?>'s Dashboard</h2>
        <hr>
<div class="goal"><h3>Total Added</h3>
<p style="font-size:2rem; font-weight:600;"><?= $total_added ?></p>
</div>
<div class="goal"><h3>Total Read</h3>
<p style="font-size:2rem; font-weight:600;"><?= $total_read ?></p>
</div>
<div class="goal" style="cursor:pointer"><a href="my_reviews.php"><h3>Reviews</h3>
<p style="font-size:2rem; font-weight:600;"><?= $total_reviews ?></p></a>
</div>
<div class="goal"><a href="my_ratings.php"><h3>Ratings</h3>
<p style="font-size:2rem; font-weight:600;"><?= $total_rated ?></p></a>
        <p style="font-size:0.8rem; color:#888;"></p>
</div>
<div class="goal" ><h3>Set Goal</h3>
    <form action="update_goal.php" method="POST">
    <input type="number" name="new_goal" placeholder="Set your goal" required>
    <button type="submit" name="save_goal">Set Goal</button>
</form>
</div>
<div class="goal">
        <h3>Progress: <?= $current_progress ?> / <?= $goal ?></h3>
        <div class="progress-container">
            <!-- PHP injects the width here -->
            <div class="progress-bar" style="width: <?= $percent ?>%;"></div>
        </div>
        <p class="percentage-label"><?= $percent ?>%</p>
</div>
</div> 
<div class="wrapper <?= $active_form === 'register' ? 'active active-popup' : ($active_form === 'login' ? 'show' : ''); ?>">
        <span class="icon-close"><ion-icon name="close"></ion-icon></span>
<div class="form-box login">
        <h2>Login</h2>
    <form action="login_register.php" method = "post">
<div class="input-box">
            <span class="icon">
                <ion-icon name="mail"></ion-icon>
            </span>
            <input type="email" name="email" id="email" required>
            <label>Email</label>
</div>
<div class="input-box">
            <span class="icon">
                <ion-icon name="lock-closed"></ion-icon>
            </span>
            <input type="password" name="password" id="password" required>
            <label>Password</label>
</div>
<div class="remember-forgot">
                <label><input type="checkbox">Remember me</label> 
                <a href="#">Forgot password?</a>
</div>
            <button type="submit" name="login" class="btn">login</button>
<div class="login-register">
            <p>Don't have an account?<a href="#" class="signup-link">Signup</a></p>
</div>
    </form> 
</div>

<div class="form-box register">
        <h2>Sign Up</h2>
    <form action="login_register.php" method="post">
        <div class="input-box">
            <span class="icon">
                <ion-icon name="person-circle-outline"></ion-icon>
            </span>
            <input type="text" name="name" id="username" required>
            <label>Username</label>
        </div>
        <div class="input-box">
            <span class="icon">
                <ion-icon name="mail"></ion-icon>
            </span>
            <input type="email" name="email" id="email" required>
            <label>Email</label>
        </div>
            <div class="input-box">
            <span class="icon">
                <ion-icon name="lock-closed"></ion-icon>
            </span>
            <input type="password" name="password" id="password" required>
            <label>Password</label>
        </div>
            <div class="remember-forgot">
             <label><input type="checkbox">I agree to the terms & conditions</label>
            </div>
            <button type="submit" name="register" class="btn">Signup</button>
        <div class="login-register">
            <p>Already have an account?<a href="#" class="login-link">login</a></p>
        </div>
    </form>
</div>

    </div>
</div>
<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </body>
</html>