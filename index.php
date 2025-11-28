<?php
// إعدادات التطبيق
define('FACEBOOK_PAGE_ACCESS_TOKEN', getenv('FACEBOOK_PAGE_ACCESS_TOKEN'));
define('FACEBOOK_GRAPH_API_URL', 'https://graph.facebook.com/v11.0/me/messages');

// الحصول على Verify Token من البيئة
$verify_token = getenv('VERIFY_TOKEN') ?: "facebook_verify_token_12345";

// تسجيل طلب الويب هوك
error_log("🌐 Webhook called: " . $_SERVER['REQUEST_METHOD']);
error_log("🔐 Verify Token expected: " . $verify_token);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // التحقق من الويب هوك
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';
    
    error_log("📡 Received verify token: " . $hub_verify_token);
    error_log("🎯 Challenge: " . $hub_challenge);
    
    if ($hub_verify_token === $verify_token) {
        error_log("✅ Verification successful!");
        echo $hub_challenge;
        exit;
    } else {
        error_log("❌ Verification failed! Expected: " . $verify_token . " | Received: " . $hub_verify_token);
        http_response_code(403);
        echo "Invalid verification token";
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // معالجة الرسائل الواردة
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("📥 Received POST webhook data");
    
    if (isset($input['object']) && $input['object'] === 'page') {
        foreach ($input['entry'] as $entry) {
            $messaging = $entry['messaging'][0] ?? [];
            if (!empty($messaging)) {
                $sender_id = $messaging['sender']['id'];
                $message = $messaging['message'] ?? [];
                
                error_log("👤 Processing message from: " . $sender_id);
                
                // رد بسيط
                send_facebook_message($sender_id, "🎉 البوت يعمل! تم استلام رسالتك بنجاح.");
            }
        }
    }
    
    echo "OK";
} else {
    http_response_code(405);
    echo "Method not allowed";
}

function send_facebook_message($recipient_id, $message_text) {
    $data = [
        "recipient" => ["id" => $recipient_id],
        "message" => ["text" => $message_text]
    ];
    
    $url = FACEBOOK_GRAPH_API_URL . "?access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("📤 Message send response: " . $http_code);
    
    return $http_code == 200;
}
?>
