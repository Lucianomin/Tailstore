
<?php
include("connect.php");




// User login logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    $sql = "SELECT password FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['username'] = $user;
        header("Location: chat.php");
        exit();
    } else {
        echo "Invalid credentials";
    }
    $new_password = 'cc12fb02-0e6';
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// Prepare and execute the update query
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'leu'");
$stmt->bind_param("s", $hashed_password);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Password updated successfully!";
} else {
    echo "Failed to update password.";
}
$stmt->close();

    $stmt->close();
}


?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f0f2f5;
        }
        .login-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        input, button {
            width: 100%;
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background: #008069;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>
