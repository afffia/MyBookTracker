<?php
session_start();
require 'config.php';

$name = $_SESSION['name'] ?? null;
$user_email = $_SESSION['email'] ?? null;
$query = trim($_GET['q'] ?? '');
$books = [];

if (!empty($query)) {
    $search = "%$query%";
    $stmt = $conn->prepare("
        SELECT id, book_title, author, genres, cover_url 
        FROM books 
        WHERE book_title LIKE ? OR author LIKE ? OR genres LIKE ?
        ORDER BY book_title ASC
        LIMIT 30
    ");
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Search — <?= htmlspecialchars($query) ?></title>
</head>
<body>

<header>
    <h1 class="logo"><a href="user_page.php">MyBookTracker</a></h1>
    <div class="user">
        <?php if ($name): ?>
        <div class="profile-box">
            <div class="avatar-circle"><?= strtoupper($name[0]) ?></div>
            <div class="drop-down">
                <a href="#">My Account</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <?php else: ?>
        <button class="btnloginpopup">Login</button>
        <?php endif; ?>
    </div>
</header>

<nav class="navbar">
    <ul>
        <li>
            <form action="search.php" method="GET">
                <div class="search">
                    <input class="search-input" type="search" name="q"
                        placeholder="Search Books..."
                        value="<?= htmlspecialchars($query) ?>"
                        autocomplete="off">
                        
                </div>
            </form>
        </li>
        <?php if ($name): ?>
        <li><a href="add_book.php">Can't find what you're looking for?</a></li>
        <li><a href="index.php">Explore</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div style="max-width:900px; margin:30px auto; padding:0 20px;">

    <?php if (empty($query)): ?>
        <p style="color:#888;">Enter a title, author, or genre to search.</p>

    <?php elseif (empty($books)): ?>
        <p style="color:#888;">No books found for "<strong><?= htmlspecialchars($query) ?></strong>".</p>
        <?php if ($name): ?>
        <p style="margin-top:8px;">
            <a href="add_book.php" style="color:#8c374f;">Add it to the catalogue →</a>
        </p>
        <?php else: ?>
        <p style="margin-top:8px; color:#888;">
            Know this book? <a href="#" class="btnloginpopup" style="color:#8c374f;">Login</a> to add it.
        </p>
        <?php endif; ?>

    <?php else: ?>
        <p style="color:#888; margin-bottom:20px;">
            <?= count($books) ?> result<?= count($books) !== 1 ? 's' : '' ?> 
            for "<strong><?= htmlspecialchars($query) ?></strong>"
        </p>

        <div style="display:flex; flex-direction:column; gap:12px;">
            <?php foreach ($books as $book): ?>
            <a href="book_detail.php?id=<?= $book['id'] ?>" style="text-decoration:none;">
                <div class="search-result-card">
                   
                    <div class="search-cover">
                        <?php if (!empty($book['cover_url'])): ?>
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="cover">
                        <?php else: ?>
                            <div class="search-cover-placeholder"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <p class="search-title"><?= htmlspecialchars($book['book_title']) ?></p>
                        <p class="search-author"><?= htmlspecialchars($book['author']) ?></p>
                        <?php if (!empty($book['genres'])): ?>
                        <p class="search-genre"><?= htmlspecialchars($book['genres']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$name): ?>
<div class="wrapper">
    <span class="icon-close"><ion-icon name="close"></ion-icon></span>
    <div class="form-box login">
        <h2>Login</h2>
        <form action="login_register.php" method="post">
            <div class="input-box">
                <span class="icon"><ion-icon name="mail"></ion-icon></span>
                <input type="email" name="email" required>
                <label>Email</label>
            </div>
            <div class="input-box">
                <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                <input type="password" name="password" required>
                <label>Password</label>
            </div>
            <div class="remember-forgot">
                <label><input type="checkbox"> Remember me</label>
                <a href="#">Forgot password?</a>
            </div>
            <button type="submit" name="login" class="btn">Login</button>
            <div class="login-register">
                <p>Don't have an account? <a href="#" class="signup-link">Signup</a></p>
            </div>
        </form>
    </div>
    <div class="form-box register">
        <h2>Sign Up</h2>
        <form action="login_register.php" method="post">
            <div class="input-box">
                <span class="icon"><ion-icon name="person-circle-outline"></ion-icon></span>
                <input type="text" name="name" required>
                <label>Username</label>
            </div>
            <div class="input-box">
                <span class="icon"><ion-icon name="mail"></ion-icon></span>
                <input type="email" name="email" required>
                <label>Email</label>
            </div>
            <div class="input-box">
                <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                <input type="password" name="password" required>
                <label>Password</label>
            </div>
            <div class="remember-forgot">
                <label><input type="checkbox"> I agree to the terms & conditions</label>
            </div>
            <button type="submit" name="register" class="btn">Signup</button>
            <div class="login-register">
                <p>Already have an account? <a href="#" class="login-link">Login</a></p>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>