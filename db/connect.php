<?php

$servername = "sql201.infinityfree.com";
$username = "if0_41479526";
$password = "a1m2r3u4t5h6a7";
$db = "if0_41479526_db_campusconnect";

// Create connection
$con = mysqli_connect($servername, $username, $password,$db);

// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}


?>
