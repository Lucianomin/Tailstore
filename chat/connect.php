<?php
$servername = "localhost";
$username = "learnluckyco_usr";
$password = "xtQir0poynEXgwcQ";
$dbname = "chat_app";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}else{
    die("success");
}

?>