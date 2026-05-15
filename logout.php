<?php

session_start();
session_unset();
session_destroy();
header('Location: tracker_homepage.php');
exit();
?>