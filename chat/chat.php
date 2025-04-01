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
/* General Styles */
body {
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    height: 100vh;
    margin: 0;
    background: #e9f1fc;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: #ffffff;
    padding: 20px;
    border-right: 1px solid #ccc;
    overflow-y: auto;
    position: fixed;
    height: 100%;
    top: 0;
    left: -100%;
    transition: left 0.3s ease-in-out;
}

.sidebar a {
    display: block;
    padding: 10px;
    margin: 5px 0;
    text-decoration: none;
    background: #3b82f6;
    color: white;
    border-radius: 5px;
    text-align: center;
}

.sidebar a:hover {
    background: #2563eb;
}

/* Chat Container */
.chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: white;
    margin-left: 250px;
    min-height: 100vh;
    position: relative;
}

/* Messages */
.messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    max-height: calc(100vh - 120px);
}

/* Message Styling */
.message {
    padding: 8px;
    margin: 5px;
    border-radius: 5px;
    max-width: 75%;
    word-wrap: break-word;
    font-size: 14px;
}

.sent {
    background: #3b82f6;
    color: white;
    align-self: flex-end;
}

.received {
    background: #e1e8f0;
    color: black;
    align-self: flex-start;
}

/* Input & Send Button */
.input-container {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: white;
    padding: 10px;
    border-top: 1px solid #ccc;
}

input {
    flex: 1;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

button {
    background: #3b82f6;
    color: white;
    border: none;
    cursor: pointer;
    padding: 10px 15px;
    border-radius: 5px;
}

button:hover {
    background: #2563eb;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 80%;
        left: -100%;
    }

    .chat-container {
        margin-left: 0;
    }

    .messages {
        max-height: calc(100vh - 100px);
    }

    .input-container {
        flex-direction: row;
        gap: 5px;
    }

    input {
        width: 70%;
    }

    button {
        width: 30%;
    }
}

/* Button to Toggle Sidebar */
#toggleSidebar {
    position: fixed;
    top: 10px;
    left: 10px;
    background: #008069;
    color: white;
    border: none;
    padding: 10px;
    cursor: pointer;
    border-radius: 5px;
}

#toggleSidebar:hover {
    background: #006657;
}

</style>

</head>
<body>

<div class="sidebar">
<button id="toggleSidebar">☰ Menu</button>

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
            <div class="refresh-container">
            <button onclick="refreshMessages()">🔄 Refresh Messages</button>
        </div>

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


document.getElementById("toggleSidebar").addEventListener("click", function() {
    var sidebar = document.querySelector(".sidebar");
    if (sidebar.style.left === "0px") {
        sidebar.style.left = "-100%";
    } else {
        sidebar.style.left = "0px";
    }
});
</script>

</body>


</html>
