<?php
$token = 'YOUR_BOT_TOKEN';
$chat_id = 'USER_CHAT_ID';  // Replace with actual chat ID

// URL to the Telegram Bot API
$api_url = "https://api.telegram.org/bot$token/";

// Function to send message
function sendMessage($text) {
    global $api_url, $chat_id;
    $url = $api_url . "sendMessage?chat_id=$chat_id&text=" . urlencode($text);
    file_get_contents($url);
}

// Function to handle user query
if (isset($_POST['message'])) {
    $user_message = $_POST['message'];
    // Connect to the database and search for the product
    include 'db.php';

    $stmt = $conn->prepare("SELECT * FROM Products WHERE item_name LIKE ?");
    $stmt->bind_param('s', $user_message);
    $stmt->execute();
    $result = $stmt->get_result();

    $response = "";
    while ($row = $result->fetch_assoc()) {
        $response .= "Product: " . $row['item_name'] . "\n";
        $response .= "Price: " . $row['offer_price'] . "\n";
        $response .= "Max Price: " . $row['max_reasonable_price'] . "\n";
        $response .= "Sales Count: " . $row['sales_count'] . "\n";
        $response .= "Reviews: " . $row['review_count'] . "\n";
        $response .= "Link: " . $row['reference_link'] . "\n\n";
    }

    if (empty($response)) {
        $response = "Product not found.";
    }

    sendMessage($response);
    $stmt->close();
    $conn->close();
}
?>
