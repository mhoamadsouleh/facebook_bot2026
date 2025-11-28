<?php
require_once 'facebook_bot.php';

// تسجيل الأخطاء
error_log("Webhook called: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // التحقق من الويب هوك
    $verify_token = getenv('VERIFY_TOKEN') ?: "Nactivi_2025";
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';
    
    error_log("Verify token: " . $hub_verify_token);
    
    if ($hub_verify_token === $verify_token) {
        echo $hub_challenge;
        exit;
    }
    http_response_code(403);
    echo "Invalid verification token";
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // معالجة الرسائل الواردة
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Received webhook data: " . json_encode($input));
    
    if (isset($input['entry'][0]['messaging'][0])) {
        $messaging = $input['entry'][0]['messaging'][0];
        $sender_id = $messaging['sender']['id'];
        $message = $messaging['message'] ?? [];
        
        error_log("Processing message from: " . $sender_id);
        
        $bot = new FacebookBot();
        $bot->handle_message($sender_id, $message);
    }
    
    echo "OK";
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>