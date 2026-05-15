<?php
session_start();
require 'config.php';
$name = $_SESSION['name'];
$email = $_SESSION['email'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: text/plain');
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo 'unauthorized';
        exit();
    }

    $id = intval($_POST['id']);

    if ($_POST['action'] === 'delete_book') {
        
        $stmt = $conn->prepare("DELETE FROM upload WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo $stmt->execute() ? 'success' : 'error';
        exit();
    }

    if ($_POST['action'] === 'delete_category') {
        
        $conn->query("DELETE FROM upload WHERE category_id = $id");
        
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo $stmt->execute() ? 'success' : 'error';
        exit();
    }
}


$cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 1;
$categories = mysqli_query($conn,"SELECT * FROM categories");
$books = mysqli_query($conn,"SELECT * FROM upload WHERE category_id='$cat_id'");
$is_admin = isset($_SESSION['role']) && trim(strtolower($_SESSION['role'] ))=== 'admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{
    margin: 0;
    padding: 0;
    box-sizing: border-box; 
}
body{
  
    background-color: #ebe1d5;
     background-image: repeating-linear-gradient(
    to bottom,
    lightgray 0px,
    lightgray 1px,
    transparent 1px,
    transparent 20px);    
}
header{
    top:0px;
    left: 0px;
    width: 100%;
    padding: 8px 60px;
    background: rgb(77, 50, 59);
    margin: 0px;
    display:flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    z-index: 9999; 
}

.logo a{  
    color: #b4979f;
}
.user .btnloginpopup {
    margin-left: 10px;
    text-decoration: none;
    padding: 6px 12px;
    border: 1px solid #d8c9cd;
    border-radius: 20px;
    color: #ebe1d5;
    border-color: #c999a7;
    background: transparent;
    cursor: pointer;
}
.profile-box .avatar-circle{
    width: 40px;
    height: 40px;
    background: #e7dede;
    border-radius: 50%;
    line-height: 40px;
    text-align: center;
    font-size: 25px;
    color: #44222f;
    font-weight: 600;
    cursor: pointer;
}

.profile-box .drop-down{
    position: absolute;
    top: 85px;
    right: 100px;
    padding: 10px;
    background: wheat;
    display: flex;
    flex-direction: column;
    transform: translateY(20px);
    opacity: 0;
    pointer-events: none;
    transition: .5s;
    z-index: 9999;
}

.profile-box.show .drop-down{
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}

.profile-box .drop-down a{
    padding: 6px 12px;
    color: #0a090a;
    text-decoration: none;
    font-weight: 500;
    margin: 2px 0;
    transition: .2s;
}

h2{
  padding:10 10 15px 20px;
}

.wrapper{
  display:flex;
  gap:20px;
  padding:0 60px 60px 0;
}

.sidebar{
  width:240px;
  height:fit-content;
  padding:20px;
  background:#fff;
  border-radius:0 8px 8px 0;
  box-shadow:0 1px 3px rgba(0,0,0,0.1);
}

.search-box{
  width:100%;
  padding:10px 12px;
  margin-bottom:15px;
  border:1px solid #ccc;
  border-radius:3px;
  background:#fafafa;
  color:#333;
  font-size:15px;
  font-weight:600;
}

.search-box::placeholder{
  color:#333;
  font-weight:600;
}

.sidebar ul{
  list-style:none;
}

.sidebar li{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:3px;
}

.sidebar li a{
  flex:1;
  display:block;
  padding:6px 10px;
  color:#00635d;
  font-size:14px;
  text-decoration:none;
  border-radius:3px;
}

.sidebar li a:hover{
  background:#f4f1ea;
  text-decoration:underline;
}

.sidebar li a.active{
  background:#615f5f;
  color:white;
  font-weight:bold;
}

.sidebar li a.active:hover{
  background:#4a4848;
  text-decoration:none;
}

.main-content{
  flex:1;
}

.books-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));
  gap:40px;
  z-index: 1;
}

.book-card{
  position:relative;
  overflow:visible;
  background:#fff;
  border-radius:4px;
  box-shadow:0 1px 3px rgba(0,0,0,0.1);
  transition:0.3s;
  z-index:0;
}

.book-card:hover{
  box-shadow:0 4px 10px rgba(0,0,0,0.2);
  z-index:10;
}

.book-card img{
  width:100%;
  height:180px;
  display:block;
  object-fit:cover;
  border-radius:4px 4px 0 0;
}

.tooltip-box{
  position:absolute;
  top:0;
  left:105%;
  width:260px;
  padding:12px;
  background:#fff;
  color:#333;
  border:1px solid #d8d8d8;
  border-radius:4px;
  box-shadow:0 2px 8px rgba(0,0,0,0.3);
  opacity:0;
  visibility:hidden;
  pointer-events:none;
  transition:opacity 0.2s, visibility 0.2s;
  z-index:999;
}

.book-card:hover .tooltip-box{
  opacity:1;
  visibility:visible;
}

.tooltip-box::before{
  content:'';
  position:absolute;
  top:15px;
  left:-8px;
  width:0;
  height:0;
  border-top:8px solid transparent;
  border-bottom:8px solid transparent;
  border-right:8px solid #d8d8d8;
}

.tooltip-box::after{
  content:'';
  position:absolute;
  top:15px;
  left:-7px;
  width:0;
  height:0;
  border-top:8px solid transparent;
  border-bottom:8px solid transparent;
  border-right:8px solid #fff;
}

.tooltip-box h3{
  margin-bottom:6px;
  color:#333;
  font-size:13px;
  font-weight:600;
}

.tooltip-box p{
  color:#555;
  font-size:11px;
  line-height:1.4;
}

.book-card:nth-child(6n) .tooltip-box{
  left:auto;
  right:105%;
}

.book-card:nth-child(6n) .tooltip-box::before{
  left:auto;
  right:-8px;
  border-right:none;
  border-left:8px solid #d8d8d8;
}

.book-card:nth-child(6n) .tooltip-box::after{
  left:auto;
  right:-7px;
  border-right:none;
  border-left:8px solid #fff;
}

.card-info{
  padding:5px;
}

.card-info h4{
  height:24px;
  margin-bottom:4px;
  overflow:hidden;
  color:#333;
  font-size:10px;
  line-height:1.2;
}

.btn{
  display:inline-block;
  padding:3px 5px;
  margin:2px 1px;
  border:none;
  border-radius:2px;
  cursor:pointer;
  font-size:9px;
  text-decoration:none;
}

.read-btn{
  background:#8b7355;
  color:white;
}

.read-btn:hover{
  background:#6d5a43;
}

.download-btn{
  background:#444;
  color:white;
}

.download-btn:hover{
  background:#2d2d2d;
}

.delete-btn{
  padding:3px 5px;
  background:#dc3545;
  color:white;
  font-size:9px;
}

.delete-btn:hover{
  background:#c82333;
}

.delete-cat-btn{
  margin-left:5px;
  padding:2px 5px;
  border:none;
  border-radius:3px;
  background:#dc3545;
  color:white;
  cursor:pointer;
  font-size:9px;
}

.delete-cat-btn:hover{
  background:#c82333;
}

.btn:disabled{
  background:#6c757d;
  cursor:not-allowed;
}
</style>
<title>Readlog</title>
</head>

<body>
<header>
        <h1 class="logo"> <a href="user_page.html">MyBookTracker</a> </h1>
           
        <div class="user">
            <?php if (!empty($name)): ?>
        <div class="profile-box">
                    <div class="avatar-circle"><?= strtoupper($name[0]) ?></div> 
                   <div class="drop-down">
   
    <a href="profile.php?email=<?= urlencode($email) ?>">My Profile</a>
    <a href="following.php">Following</a>
    <a href="user_page.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</div>
                </div>
                <?php else: ?>
                 <button class="btnloginpopup" name="login_btn">Login</button>
                 <?php endif; ?>
             </div></header>
<h2>Explore</h2>

<div class="wrapper">
<div class="sidebar">
  <input type="text" class="search-box" placeholder="Genres" readonly>
  <ul>
    <?php while($c = mysqli_fetch_assoc($categories)) { ?>
      <li>
        <a href="?cat_id=<?php echo $c['id']; ?>" class="<?php echo ($cat_id == $c['id']) ? 'active' : ''; ?>">
          <?php echo htmlspecialchars($c['name']); ?>
        </a>
        <?php if($is_admin): ?>
        <button class="delete-cat-btn" data-id="<?php echo $c['id']; ?>" title="Delete Category">
          <i class="fa fa-trash"></i>
        </button>
        <?php endif; ?>
      </li>
    <?php } ?>
  </ul>
</div>

<div class="main-content">
  <div class="books-grid">
    <?php while($b = mysqli_fetch_assoc($books)) { ?>
      <div class="book-card" id="book-<?php echo $b['id']; ?>">
        
        <img src="uploads/covers/<?php echo htmlspecialchars($b['cover_image']); ?>" alt="<?php echo htmlspecialchars($b['title']); ?>">
        
        <div class="tooltip-box">
          <h3><?php echo htmlspecialchars($b['title']); ?></h3>
          <p><?php echo htmlspecialchars($b['description']); ?></p>
        </div>

        <div class="card-info">
          <h4><?php echo htmlspecialchars($b['title']); ?></h4>
          <a href="uploads/pdfs/<?php echo htmlspecialchars($b['pdf_file']); ?>" target="_blank" class="btn read-btn">Read</a>
          <a href="uploads/pdfs/<?php echo htmlspecialchars($b['pdf_file']); ?>" download class="btn download-btn">Download</a>
          <?php if($is_admin): ?>
          <button class="btn delete-btn delete-book-btn" data-id="<?php echo $b['id']; ?>">
            <i class="fa fa-trash"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on('click', '.delete-book-btn', function(){
if(!confirm('Do you want to delete this book?')) return;    let btn = $(this), id = btn.data('id'), card = $('#book-' + id);
    btn.text('...').prop('disabled', true);
    
    $.post('category.php', {action: 'delete_book', id: id}, function(res){
        if(res.trim() == 'success') card.fadeOut(300, function(){ $(this).remove(); });
        else {alert('Failed to delete book');
         btn.html('<i class="fa fa-trash"></i>').prop('disabled', false); }
    });
});

$(document).on('click', '.delete-cat-btn', function(){
  if(!confirm('Delete this category? All books inside it will also be deleted!')) return;
if(!confirm('Do you want to delete this book?')) return;
let btn = $(this),
    id = btn.data('id'),
    card = $('#book-' + id);
    $.post('category.php', {action: 'delete_category', id: id}, function(res){
        if(res.trim() == 'success') location.reload(); 
        else alert('Failed to delete category');
    });
});
</script>
</body>
</html>