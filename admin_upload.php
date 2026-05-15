<?php
require 'config.php';

if(isset($_POST['upload'])){
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $category_id = $_POST['category_id'];

    $cover = time().$_FILES['cover']['name'];
    $pdf = time().$_FILES['pdf']['name'];

    move_uploaded_file($_FILES['cover']['tmp_name'], "uploads/covers/".$cover);
    move_uploaded_file($_FILES['pdf']['tmp_name'], "uploads/pdfs/".$pdf);

    $sql = "INSERT INTO upload (title,description,cover_image,pdf_file,category_id)
            VALUES ('$title','$desc','$cover','$pdf','$category_id')";

    mysqli_query($conn,$sql);

    echo "<script>alert('Book Uploaded Successfully');</script>";
}

if(isset($_POST['ajax_add_category'])){
    $cat_name = mysqli_real_escape_string($conn, $_POST['cat_name']);

    mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$cat_name')");

    $new_id = mysqli_insert_id($conn);

    echo json_encode(['id' => $new_id, 'name' => $cat_name]);
    exit;
}

$categories = mysqli_query($conn,"SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI', Arial, sans-serif;
    background-color:#ebe1d5;
    background-image:repeating-linear-gradient(
        to bottom,
        lightgray 0px,
        lightgray 1px,
        transparent 1px,
        transparent 20px
    );
    color:#9f3855;
}

h1{
    color:#e099af;
    margin:10px 60px 15px;
}

.addbookbg{
    display:flex;
    justify-content:center;
    align-items:center;
}

.bookForm{
    width:70%;
    padding:20px 50px;
    background:rgba(189,128,154,0.3);
    backdrop-filter:blur(0.2px);
    border-radius:20px;
    border:1px solid #e0b899;
}

.bookForm input,
.bookForm select{
    width:100%;
    padding:8px;
    margin:4px 0 12px;
    border-radius:15px;
    border:1px solid #bd486b;
}

.bookForm button{
    padding:10px 20px;
    border:none;
    background:#ede7e7;
    color:#bd486b;
    border-radius:4px;
    cursor:pointer;
}

.bookForm button:hover{
    background:#a64565;
    color:white;
}


.select-with-icon{
    position:relative;
    width:100%;
}


.select-with-icon select{
    width:100%;
    padding:8px 45px 8px 10px;
    margin:4px 0 12px;
    border-radius:15px;
    border:1px solid #bd486b;
    background:#fff;

    appearance:none;
    -webkit-appearance:none;
    -moz-appearance:none;

    color:transparent;
    text-shadow:0 0 0 #9f3855;
}

/* OPTIONS TEXT */
.select-with-icon select option{
    color:#9f3855;
}


.select-with-icon::after{
    content:"▼";
    position:absolute;
    right:35px;
    top:45%;
    transform:translateY(-50%);
    font-size:15px;
    color:#bd486b;
    pointer-events:none;
}

.plus-icon{
    position:absolute;
    right:10px;
    top:40%;
    transform:translateY(-50%);
    cursor:pointer;
    font-size:22px;
    color:#bd486b;
}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
}

.modal-content{
    background:#fff;
    width:400px;
    margin:150px auto;
    padding:20px;
    border-radius:10px;
    position:relative;
}

.close{
    position:absolute;
    right:15px;
    top:10px;
    font-size:28px;
    cursor:pointer;
}

.modal-content input{
    width:100%;
    padding:8px;
    margin:10px 0;
}
</style>
</head>

<body>

<h1>Admin Book Upload</h1>

<div class="addbookbg">
<div class="bookForm">

<form method="POST" enctype="multipart/form-data">

    <label>Book Title:</label>
    <input type="text" name="title" required>

    <label>Description:</label>
    <input type="text" name="description">

    <label>Category:</label>

    <div class="select-with-icon">

        <select name="category_id" id="category_select" required>
            <option value="" selected hidden></option>

            <?php while($row = mysqli_fetch_assoc($categories)) { ?>
                <option value="<?php echo $row['id']; ?>">
                    <?php echo $row['name']; ?>
                </option>
            <?php } ?>

        </select>

        <span class="plus-icon" onclick="openModal()">+</span>

    </div>

    <label>Book Cover:</label>
    <input type="file" name="cover" required>

    <label>PDF:</label>
    <input type="file" name="pdf" required>

    <button type="submit" name="upload">Upload Book</button>

</form>

</div>
</div>

<div id="catModal" class="modal">
    <div class="modal-content">

        <span class="close" onclick="closeModal()">&times;</span>

        <h3>Add Category</h3>
        <input type="text" id="new_cat_name" placeholder="Category Name">
        <button onclick="addCategory()">Save</button>

    </div>
</div>

<script>
function openModal(){
    document.getElementById('catModal').style.display='block';
}

function closeModal(){
    document.getElementById('catModal').style.display='none';
    document.getElementById('new_cat_name').value='';
}

function addCategory(){

    let catName = document.getElementById('new_cat_name').value.trim();

    if(catName==''){
        alert('Enter category name');
        return;
    }

    let formData = new FormData();
    formData.append('ajax_add_category','1');
    formData.append('cat_name',catName);

    fetch('admin_upload.php',{
        method:'POST',
        body:formData
    })
    .then(res=>res.json())
    .then(data=>{

        let select=document.getElementById('category_select');

        let option=document.createElement('option');
        option.value=data.id;
        option.text=data.name;
        option.selected=true;

        select.appendChild(option);

        closeModal();
        alert('Category Added');

    })
    .catch(()=>{
        alert('Error');
    });
}

window.onclick=function(e){
    let modal=document.getElementById('catModal');
    if(e.target==modal){
        closeModal();
    }
}
</script>

</body>
</html>