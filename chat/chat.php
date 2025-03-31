<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include("connect.php");

$user = $_SESSION['username'];
$selectedUser = isset($_GET['user']) ? $_GET['user'] : "";

// Funcții de criptare și decriptare
function encryptMessage($message, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($message, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptMessage($encryptedMessage, $key) {
    list($encryptedData, $iv) = explode('::', base64_decode($encryptedMessage), 2);
    return openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);
}

// Fetch all users except the logged-in user
$sql_users = "SELECT username FROM users WHERE username != ?";
$stmt_users = $conn->prepare($sql_users);
$stmt_users->bind_param("s", $user);
$stmt_users->execute();
$users_result = $stmt_users->get_result();

// Fetch messages if a user is selected
$messages = [];
if ($selectedUser) {
    $sql_messages = "SELECT * FROM messages 
                     WHERE (sender=? AND receiver=?) OR (sender=? AND receiver=?) 
                     ORDER BY timestamp ASC";
    $stmt_messages = $conn->prepare($sql_messages);
    $stmt_messages->bind_param("ssss", $user, $selectedUser, $selectedUser, $user);
    $stmt_messages->execute();
    $messages_result = $stmt_messages->get_result();
    
    while ($row = $messages_result->fetch_assoc()) {
        // Decriptăm mesajul
        $decryptedMessage = decryptMessage($row['message'], $secretKey);

        $messages[] = array(
            'sender' => $row['sender'],
            'message' => $decryptedMessage,
            'timestamp' => $row['timestamp']
        );
    }
}

// Send message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send']) && $selectedUser) {
    $message = $_POST['message'];
    if (!empty($message)) {
        // Criptăm mesajul înainte de a-l salva
        $encryptedMessage = encryptMessage($message, $secretKey);

        $stmt = $conn->prepare("INSERT INTO messages (sender, receiver, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $selectedUser, $encryptedMessage);
        $stmt->execute();
        $stmt->close();

        header("Location: chat.php?user=" . urlencode($selectedUser));
        exit();
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Chat</title>
    <style>
    body {
    font-family: Arial, sans-serif;
    display: flex;
    height: 100vh;
    margin: 0;
    background: #e9f1fc; /* Light blue background */
}

.sidebar {
    width: 25%;
    background: #ffffff;
    padding: 20px;
    border-right: 1px solid #ccc;
    overflow-y: auto;
    position: fixed; /* Keep the sidebar fixed */
    height: 100%;
    top: 0;
}

.sidebar a {
    display: block;
    padding: 10px;
    margin: 5px 0;
    text-decoration: none;
    background: #3b82f6; /* Blue background */
    color: white;
    border-radius: 5px;
    text-align: center;
}

.sidebar a:hover {
    background: #2563eb; /* Darker blue on hover */
}

.chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px;
    background: white;
    margin-left: 25%; /* Offset chat container to avoid overlap with sidebar */
    min-height: 100vh;
    position: relative;
}

.messages {
    flex: 1;
    overflow-y: auto;
    max-height: 500px;
    padding-bottom: 20px;
}

.message {
    padding: 10px;
    margin: 5px;
    border-radius: 5px;
    max-width: 75%;
    word-wrap: break-word;
}

.sent {
    background: #3b82f6; /* Blue background for sent messages */
    color: white;
    align-self: flex-end;
}

.received {
    background: #e1e8f0; /* Light blue background for received messages */
    color: black;
    align-self: flex-start;
}

.input-container {
    display: flex;
    margin-top: 10px;
}

input, button {
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

input {
    flex: 1;
}

button {
    background: #3b82f6; /* Blue button background */
    color: white;
    border: none;
    cursor: pointer;
}

button:hover {
    background: #2563eb; /* Darker blue on hover */
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        position: relative;
        border-right: none;
        margin-bottom: 20px;
    }

    .chat-container {
        margin-left: 0;
    }

    .messages {
        max-height: 400px; /* Adjust the max-height for smaller screens */
    }

    .message {
        max-width: 90%; /* Increase message width on smaller screens */
    }

    button {
        width: 100%; /* Make button full width */
    }
}

@media (max-width: 480px) {
    .sidebar a {
        font-size: 14px; /* Make sidebar links smaller */
    }

    .input-container {
        flex-direction: column;
    }

    input, button {
        width: 100%; /* Make input and button full width */
    }
}

        /* #chat-box {
    overflow-y: auto; 
    max-height: 500px; 
    display: flex;
    flex-direction: column-reverse; 
} */

    </style>
</head>
<body>

<div class="sidebar">
<div style="padding: 10px; background: #ddd; border-radius: 5px; text-align: center; margin-bottom: 10px;">
    Logged in as: <strong><?php echo htmlspecialchars($user); ?></strong>
</div>

    <h3>Users</h3>
    <?php while ($row = $users_result->fetch_assoc()): ?>
        <a href="chat.php?user=<?php echo urlencode($row['username']); ?>">
            <?php echo htmlspecialchars($row['username']); ?>
        </a>
    <?php endwhile; ?>
</div>


<div class="chat-container" >
    <!-- Butonul care va duce utilizatorul la capătul chat-ului -->
<button onclick="scrollToBottom()" style="background: #008069; color: white; padding: 10px; border-radius: 5px; border: none; cursor: pointer;">
    Scroll To New Message
</button>
    <?php if ($selectedUser): ?>
        <h2>Chat with <?php echo htmlspecialchars($selectedUser); ?></h2>
            <div class="messages" id="chat-box">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender'] == $user ? 'sent' : 'received'; ?>">
                        <strong><?php echo htmlspecialchars($msg['sender']); ?>:</strong>
                        <?php echo htmlspecialchars($msg['message']); ?>
                        <br><small><?php echo $msg['timestamp']; ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <button onclick="refreshMessages()" style="background: #008069; color: white; padding: 10px; border-radius: 5px; border: none; cursor: pointer;">
    Refresh Messages
</button>

        <form method="POST" class="input-container">
            <input type="text" name="message" placeholder="Type a message..." required>
            <button type="submit" name="send">Send</button>
        </form>
    <?php else: ?>
        <h2>Select a user to start chatting</h2>
    <?php endif; ?>
</div>
<script>
function refreshMessages() {
    var selectedUser = "<?php echo $selectedUser; ?>";
    if (selectedUser !== "") {
        var chatBox = document.getElementById("chat-box");
        
        // Verifică dacă utilizatorul este la capătul conversației
        var isScrolledToBottom = chatBox.scrollHeight - chatBox.scrollTop === chatBox.clientHeight;
        
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "fetch_messages.php?user=" + selectedUser, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                chatBox.innerHTML = xhr.responseText;

                // Dacă utilizatorul era la capătul conversației, păstrează-l acolo
                if (isScrolledToBottom) {
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            }
        };
        xhr.send();
        
    }
}

// Reîmprospătează mesajele la fiecare 2 secunde
//setInterval(refreshMessages, 2000);

// Asigură-te că la încărcarea paginii chat-ul începe la final
window.onload = function() {
    var chatBox = document.getElementById("chat-box");
    chatBox.scrollTop = chatBox.scrollHeight;
};

function scrollToBottom() {
    var chatBox = document.getElementById("chat-box");
    chatBox.scrollTop = chatBox.scrollHeight; // Setează poziția de scroll la capătul chat-ului
}

</script>

</body>


</html>
