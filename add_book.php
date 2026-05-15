<?php
session_start();
require 'config.php';

if (!isset($_SESSION['name'])) {
    $_SESSION['alerts'][] = ['type' => 'error', 'message' => 'You must be logged in to add a book.'];
    header("Location: homepage.php");
    exit();
}

$name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$warning = '';
$disableUntil = null;
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
$time_check = $conn->prepare("SELECT MAX(created_at) as last FROM books WHERE added_by_email = ?");
$time_check->bind_param("s", $user_email);
$time_check->execute();
$last_added = $time_check->get_result()->fetch_assoc()['last'];

if ($last_added) {
    $next_allowed = strtotime($last_added) + (0 * 00 * 60);
    if ($next_allowed > time()) {
        $disableUntil = $next_allowed;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($disableUntil && $disableUntil > time()) {
        $warning = "You can add the next book after 4 hours.";
    } else {

        $title        = trim($_POST['title'] ?? '');
        $author       = trim($_POST['author'] ?? '');
        $synopsis     = trim($_POST['synopsis'] ?? '');
        $language     = $_POST['language'] ?? 'English';
        $publish_date = $_POST['publish_date'] ?? null;
        $publisher    = trim($_POST['publisher'] ?? '');
        $chapters     = intval($_POST['chapters'] ?? 0);
        $status       = $_POST['status'] ?? 'Completed';
        $source_value = trim($_POST['link'] ?? '');

        if (empty($title) || empty($author)) {
            $warning = "Title and Author are required.";
        } else {
            
            $check = $conn->prepare("SELECT id FROM books WHERE book_title = ? AND author = ?");
            $check->bind_param("ss", $title, $author);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $warning = "This book already exists!";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO books 
                    (book_title, author, synopsis, language, publish_date, publisher, chapters, status, source_value, added_by_email)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssssisss",
                    $title,
                    $author,
                    $synopsis,
                    $language,
                    $publish_date,
                    $publisher,
                    $chapters,
                    $category,
                    $source_value,
                    $user_email
                );
                $stmt->execute();
                $new_id = $conn->insert_id;
                $stmt->close();

                $_SESSION['alerts'][] = ['type' => 'success', 'message' => "\"$title\" added successfully!"];
                header("Location: book_detail.php?id=$new_id");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="style.css">



<title>Add Book</title>
</head>

<body>

<header>
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

<div class="top-left">
  <h2>Add New Book</h2>
  <p>Expand your literary collection</p>
</div>

<?php if ($warning): ?>
<div class="error"><?= htmlspecialchars($warning) ?></div>
<?php endif; ?>

<div class="addbookbg">
  <div class="bookForm">

<form method="POST" onsubmit="return validateForm()">

<div class="form-grid">

  <div>
    <label>Title</label>
    <input type="text" id="title" name="title">
  </div>

  <div>
    <label>Author</label>
    <input type="text" id="author" name="author">
  </div>

  <div class="full">
    <label>Synopsis</label>
    <textarea name="synopsis"></textarea>
  </div>

  <div>
    <label>Language</label>
    <select name="language">
      <option>English</option>
      <option>Bangla</option>
    </select>
  </div>

  <div>
    <label>Publish Date</label>
    <input type="date" name="publish_date">
  </div>

  <div>
    <label>Publisher</label>
    <input type="text" name="publisher">
  </div>

  <div>
    <label>Chapters</label>
    <input type="number" name="chapters">
  </div>

 <div>
    <label>Category</label>
    <select name="category">
        <option value="published novel">Published Novel</option>
        <option value="webnovel">Webnovel</option>
        <option value="manga">Manga</option>
        <option value="manhwa">Manhwa</option>
    </select>
</div>

<div>
    <label>Cover Image URL</label>
    <input type="url" name="cover_url" placeholder="https://...">
</div>

  <div class="full">
    <label>Source</label><br>
    <input type="url" name="link" placeholder="Book link">
  </div>

</div>

<button id="submitBtn" class="submitBtn">Submit</button>

</form>

  </div>
</div>

<div class="daily-limit floating">
  <i class="fa-solid fa-clock"></i>
  <span id="timer">Every 4 hours</span>
</div>
<script src="script.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<script>
const submitBtn = document.getElementById('submitBtn');
const disableUntil = <?= json_encode($disableUntil) ?>;
const timerEl = document.getElementById('timer');

function validateForm(){
  const title = document.getElementById('title').value.trim();
  const author = document.getElementById('author').value.trim();

  if(!title || !author){
    alert("Title & Author required");
    return false;
  }
  return true;
}

function updateTimer(){

  if(!disableUntil){
    timerEl.innerHTML = "Available to add book";
    submitBtn.disabled = false;
    return;
  }

  const now = Math.floor(Date.now()/1000);
  const diff = disableUntil - now;

  if(diff <= 0){
    submitBtn.disabled = false;
    timerEl.innerHTML = "You can add book now";
    return;
  }

  submitBtn.disabled = true;

  const h = Math.floor(diff/3600);
  const m = Math.floor((diff%3600)/60);
  const s = diff%60;

  timerEl.innerText =
    `${h.toString().padStart(2,'0')}:` +
    `${m.toString().padStart(2,'0')}:` +
    `${s.toString().padStart(2,'0')}`;
}

updateTimer();
setInterval(updateTimer, 1000);
</script>

</body>
</html>