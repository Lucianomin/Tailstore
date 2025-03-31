
<?php
session_start();
include("connect.php");

if (!isset($_SESSION['username']) || !isset($_GET['user'])) {
    exit();
}

$user = $_SESSION['username'];
$selectedUser = $_GET['user'];

// Funcție pentru decriptare
function decryptMessage($encryptedMessage, $key) {
    list($encryptedData, $iv) = explode('::', base64_decode($encryptedMessage), 2);
    return openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);
}

$sql_messages = "SELECT * FROM messages 
                 WHERE (sender=? AND receiver=?) OR (sender=? AND receiver=?) 
                 ORDER BY timestamp ASC";
$stmt_messages = $conn->prepare($sql_messages);
$stmt_messages->bind_param("ssss", $user, $selectedUser, $selectedUser, $user);
$stmt_messages->execute();
$messages_result = $stmt_messages->get_result();
?>
<style>
     /* #chat-box {
    overflow-y: auto; 
    max-height: 500px; 
    display: flex;
    flex-direction: column-reverse; 
} */
</style>
<div class="messages" id="chat-box" >
    <?php while ($msg = $messages_result->fetch_assoc()): ?>
        <?php $decryptedMessage = decryptMessage($msg['message'], $secretKey); ?>
        <div  class="message <?php echo $msg['sender'] == $user ? 'sent' : 'received'; ?>">
            <strong><?php echo htmlspecialchars($msg['sender']); ?>:</strong>
            <?php echo htmlspecialchars($decryptedMessage); ?>
            <br><small><?php echo $msg['timestamp']; ?></small>
        </div>
    <?php endwhile; ?>
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

</script>