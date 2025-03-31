<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/vendor/autoload.php'; // Încarcă Composer
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load(); // Încarcă variabilele din .env

$servername = "web.tenue.one";
$username = "chat_app_usr";
$password = "xtQir0poynEXgwcQ";
$dbname = "chat_app";

// Cheia secretă este acum protejată și încărcată din .env
$secretKey = $_ENV['SECRET_KEY'];

// Creare conexiune
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
