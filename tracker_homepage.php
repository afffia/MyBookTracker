<?php
session_start();
require 'config.php';

// If already logged in, redirect to user page
if (isset($_SESSION['name'])) {
    header("Location: user_page.php");
    exit();
}

$name = $_SESSION['name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';
session_unset();
if($name !== null) $_SESSION['name']=$name;
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
        <h1 class="logo"> <a href="tracker_page.html">MyBookTracker</a> </h1>
           
        <div class="user">
            <?php if (!empty($name)): ?>
        <div class="profile-box">
                    <div class="avatar-circle"><?= strtoupper($name[0]) ?></div> <!-- [7, 8] -->
                    <div class="drop-down">
                        <a href="#">My Account</a>
                        <a href="logout.php">Logout</a> <!-- [9, 10] -->
                    </div>
                </div>
                <?php else: ?>
                 <button class="btnloginpopup" name="login_btn">Login</button>
                 <?php endif; ?>
             </div></header>
<nav class="navbar"> <ul>
        <li><form action="search.php" method="GET">
                <div class="search">
                    <input class="search-input" type="search" name="q" 
                        placeholder="Search Books..." 
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        autocomplete="off">
                </div>
            </form></li>
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

<section><h1 style="text-align: center; top: 80%;">Welcome <?= $name ?? 'Reader'?>!</h1>
    <h2 style="text-align:center;">Get started with your journey</h2>
</section>

<div class="page-background">

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