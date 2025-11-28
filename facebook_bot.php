<?php
// إعدادات التطبيق - سيتم تعبئتها من البيئة
define('FACEBOOK_PAGE_ACCESS_TOKEN', getenv('FACEBOOK_PAGE_ACCESS_TOKEN') ?: 'EAARRlvmJ1MMBQKnSwYyhngmN7DDCtHP3LuNfQORrh1X6xzr7xI2fDJ0PjJvSaysjnG90EXvuJhRuYE80pF81GK1z9py3Xy7hRruOs0T9tebP3asDajzHmcwd8RBB4jyi2NfKZCsYytKGLZBq916Agrd4QXZCU8f26WH0xwJUkZAVnALZAcSOisILos40dJv1qBE7cGKSYswZDZD');
define('FACEBOOK_GRAPH_API_URL', 'https://graph.facebook.com/v11.0/me/messages');

// إعدادات APIs
define('CHAT_API_URL', "https://prod-smith.vulcanlabs.co/api/v7/chat_android");
define('VISION_API_URL', "https://api.vulcanlabs.co/smith-v2/api/v7/vision_android");
define('GETIMG_API_URL', "https://api.getimg.ai/v1/stable-diffusion-xl/text-to-image");
define('GETIMG_API_KEY', getenv('GETIMG_API_KEY') ?: "key-3XbWkFO34FVCQUnJQ6A3qr702Eu7DDR1dqoJOyhMHqhruEhs22KUzR7w631ZFiA5OFZIba7i44qDQEMpKxzegOUm83vCfILb");
define('VISION_AUTH_TOKEN', "FOcsaJJf1A+Zh3Ku6EfaNYbo844Y7168Ak2lSmaxtNZVtD7vcaJUmTCayc1HgcXIILvdmnzsdPjuGwqYKKUFRLdUVQQZbfXHrBUSYrbHcMrmxXvDu/DHzrtkPqg90dX/rSmTRnx7sz7pHTOmZqLLfLUnaO2XTEZLD0deMpRdzQE=");
define('ASSEMBLYAI_API_KEY', getenv('ASSEMBLYAI_API_KEY') ?: "771de44ac7644510a0df7e9a3b8a6b7c");
define('TTS_SERVICE_URL', "https://dev-yacingpt.pantheonsite.io/wp-admin/maint/Bot%20hosting/Textspeesh.php");

// التخزين المحلي للمستخدمين
$user_conversations = [];
$current_access_token = null;
$running = true;
$processed_message_ids = [];

class FacebookBot {
    private $user_conversations;
    private $current_access_token;
    private $running;
    private $processed_message_ids;
    
    public function __construct() {
        $this->user_conversations = [];
        $this->current_access_token = null;
        $this->running = true;
        $this->processed_message_ids = [];
        
        // إعدادات إضافية للخادم
        set_time_limit(0);
        ignore_user_abort(true);
    }
    
    public function send_typing_indicator($recipient_id, $typing_status = true) {
        $action = $typing_status ? "typing_on" : "typing_off";
        $data = [
            "recipient" => ["id" => $recipient_id],
            "sender_action" => $action
        ];
        
        try {
            $response = $this->make_api_request(
                FACEBOOK_GRAPH_API_URL . "?access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN,
                'POST',
                $data
            );
            return $response['http_code'] == 200;
        } catch (Exception $e) {
            error_log("Typing indicator error: " . $e->getMessage());
            return false;
        }
    }
    
    public function wait_seconds($seconds) {
        sleep($seconds);
    }
    
    public function get_access_token($force_refresh = false) {
        global $current_access_token;
        
        if (!$force_refresh && $this->current_access_token) {
            return $this->current_access_token;
        }
        
        $url = "https://chatgpt-au.vulcanlabs.co/api/v1/token";
        $headers = [
            "Host: chatgpt-au.vulcanlabs.co",
            "x-vulcan-application-id: com.smartwidgetlabs.chatgpt",
            "accept: application/json",
            "user-agent: Chat Smith Android, Version 3.8.0(602)",
            "x-vulcan-request-id: 9149487891720485306508",
            "content-type: application/json; charset=utf-8",
            "accept-encoding: gzip"
        ];
        
        $payload = [
            "device_id" => "F75FA09A4ECFF631",
            "order_id" => "",
            "product_id" => "",
            "purchase_token" => "",
            "subscription_id" => ""
        ];
        
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = $this->make_api_request($url, 'POST', $payload, $headers);
                if ($response['http_code'] == 200) {
                    $data = json_decode($response['body'], true);
                    $this->current_access_token = $data['AccessToken'] ?? null;
                    return $this->current_access_token;
                }
            } catch (Exception $e) {
                error_log("Attempt " . ($attempt + 1) . " failed: " . $e->getMessage());
                $this->wait_seconds(pow(2, $attempt));
            }
        }
        
        error_log("Failed to get access token");
        return null;
    }
    
    public function token_refresh_scheduler() {
        while ($this->running) {
            $this->wait_seconds(900); // انتظار 15 دقيقة
            if ($this->running) {
                echo "Refreshing token...\n";
                $this->get_access_token(true);
            }
        }
    }
    
    public function send_chat_request($messages, $retry_count = 0) {
        if (!$this->current_access_token) {
            $this->current_access_token = $this->get_access_token();
            if (!$this->current_access_token) {
                return null;
            }
        }
        
        $headers = [
            "Host: prod-smith.vulcanlabs.co",
            "authorization: Bearer " . $this->current_access_token,
            "x-firebase-appcheck-error: -2%3A+Integrity+API+error...",
            "x-vulcan-application-id: com.smartwidgetlabs.chatgpt",
            "accept: application/json",
            "user-agent: Chat Smith Android, Version 3.8.0(602)",
            "x-vulcan-request-id: 9149487891720485379249",
            "content-type: application/json; charset=utf-8",
            "accept-encoding: gzip"
        ];
        
        $payload = [
            "model" => "gpt-4",
            "user" => "F75FA09A4ECFF631",
            "messages" => $messages,
            "nsfw_check" => true,
            "functions" => [
                [
                    "name" => "create_ai_art",
                    "description" => "Return this only if the user wants to create a photo or art...",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "prompt" => [
                                "type" => "string",
                                "description" => "The prompt to create art"
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        try {
            $response = $this->make_api_request(CHAT_API_URL, 'POST', $payload, $headers, 15);
            
            if ($response['http_code'] == 401 && $retry_count < 2) {
                echo "Token expired, refreshing...\n";
                $this->current_access_token = $this->get_access_token(true);
                if ($this->current_access_token) {
                    return $this->send_chat_request($messages, $retry_count + 1);
                }
            }
            
            if ($response['http_code'] == 200) {
                return json_decode($response['body'], true);
            }
            return null;
        } catch (Exception $e) {
            error_log("Chat request error: " . $e->getMessage());
            return null;
        }
    }
    
    public function transcribe_audio($audio_url) {
        try {
            $data = [
                "audio_url" => $audio_url,
                "language_code" => "ar",
                "speech_model" => "nano"
            ];
            
            $headers = [
                "authorization: " . ASSEMBLYAI_API_KEY,
                "content-type: application/json"
            ];
            
            $response = $this->make_api_request(
                "https://api.assemblyai.com/v2/transcript",
                'POST',
                $data,
                $headers
            );
            
            if ($response['http_code'] != 200) {
                return null;
            }
            
            $result = json_decode($response['body'], true);
            $transcript_id = $result['id'] ?? null;
            
            if (!$transcript_id) {
                return null;
            }
            
            $polling_url = "https://api.assemblyai.com/v2/transcript/" . $transcript_id;
            
            while (true) {
                $poll_response = $this->make_api_request($polling_url, 'GET', null, $headers);
                $result = json_decode($poll_response['body'], true);
                
                if ($result['status'] == 'completed') {
                    return $result['text'];
                } elseif ($result['status'] == 'error') {
                    return null;
                }
                $this->wait_seconds(1);
            }
        } catch (Exception $e) {
            error_log("Transcription error: " . $e->getMessage());
            return null;
        }
    }
    
    public function text_to_speech($text, $sender_id) {
        try {
            $payload = http_build_query(['text' => $text]);
            $headers = ['Content-Type: application/x-www-form-urlencoded'];
            
            $response = $this->make_api_request(TTS_SERVICE_URL, 'POST', $payload, $headers);
            
            if ($response['http_code'] != 200) {
                return null;
            }
            
            $result = json_decode($response['body'], true);
            if (isset($result['audio_url'])) {
                $audio_response = $this->make_api_request($result['audio_url'], 'GET');
                if ($audio_response['http_code'] == 200) {
                    return $audio_response['body'];
                }
            }
            return null;
        } catch (Exception $e) {
            error_log("TTS error: " . $e->getMessage());
            return null;
        }
    }
    
    public function process_image($image_url, $sender_id) {
        try {
            $this->send_typing_indicator($sender_id, true);
            
            $image_response = $this->make_api_request($image_url, 'GET');
            if ($image_response['http_code'] != 200) {
                $this->send_facebook_message($sender_id, "❌ لم أتمكن من تحميل الصورة");
                $this->send_typing_indicator($sender_id, false);
                return null;
            }
            
            $image_data = $image_response['body'];
            $boundary = "44cb511a-c1d4-4f51-a017-1352f87db948";
            
            $headers = [
                "Host: api.vulcanlabs.co",
                "x-auth-token: " . VISION_AUTH_TOKEN,
                "authorization: Bearer " . $this->current_access_token,
                "x-firebase-appcheck-error: -9%3A+Integrity+API",
                "x-vulcan-application-id: com.smartwidgetlabs.chatgpt",
                "accept: application/json",
                "user-agent: Chat Smith Android, Version 3.9.11(720)",
                "x-vulcan-request-id: 9149487891748042373127",
                "content-type: multipart/form-data; boundary=" . $boundary,
                "accept-encoding: gzip"
            ];
            
            $data_part = "--" . $boundary . "\r\n" .
                "Content-Disposition: form-data; name=\"data\"\r\n" .
                "Content-Length: 145\r\n\r\n" .
                '{"model":"gpt-4o-mini","user":"F75FA09A4ECFF631","messages":[{"role":"user","content":"ما هذا وعلى ما يحتوي"}],"nsfw_check":true}' . "\r\n";
            
            $image_part = "--" . $boundary . "\r\n" .
                "Content-Disposition: form-data; name=\"images[]\"; filename=\"uploaded_image.jpg\"\r\n" .
                "Content-Type: image/jpeg\r\n\r\n";
            
            $end_boundary = "\r\n--" . $boundary . "--\r\n";
            
            $body = $data_part . $image_part . $image_data . $end_boundary;
            
            $response = $this->make_api_request(VISION_API_URL, 'POST', $body, $headers);
            
            if ($response['http_code'] == 401) {
                $this->current_access_token = $this->get_access_token(true);
                if ($this->current_access_token) {
                    $headers[2] = "authorization: Bearer " . $this->current_access_token;
                    $new_response = $this->make_api_request(VISION_API_URL, 'POST', $body, $headers);
                    if ($new_response['http_code'] == 200) {
                        $result = json_decode($new_response['body'], true);
                        $this->send_typing_indicator($sender_id, false);
                        
                        foreach ($result['choices'] ?? [] as $choice) {
                            if (isset($choice['Message']['content'])) {
                                return $choice['Message']['content'];
                            }
                        }
                        return null;
                    }
                }
            }
            
            if ($response['http_code'] == 200) {
                $result = json_decode($response['body'], true);
                $this->send_typing_indicator($sender_id, false);
                
                foreach ($result['choices'] ?? [] as $choice) {
                    if (isset($choice['Message']['content'])) {
                        return $choice['Message']['content'];
                    }
                }
                return null;
            }
            
            $this->send_typing_indicator($sender_id, false);
            return null;
        } catch (Exception $e) {
            error_log("Image processing error: " . $e->getMessage());
            $this->send_typing_indicator($sender_id, false);
            return null;
        }
    }
    
    public function generate_images($prompt) {
        $headers = [
            'Authorization: Bearer ' . GETIMG_API_KEY,
            'Content-Type: application/json',
        ];
        
        $data = [
            'model' => 'realvis-xl-v4',
            'prompt' => $prompt,
            'negative_prompt' => 'nude, naked, porn, sexual, explicit, adult, sex, xxx, erotic',
            'response_format' => 'url',
            'steps' => 30,
            'height' => 1024,
            'width' => 1024
        ];
        
        try {
            $response = $this->make_api_request(GETIMG_API_URL, 'POST', json_encode($data), $headers);
            if ($response['http_code'] == 200) {
                $result = json_decode($response['body'], true);
                return $result['url'] ?? null;
            }
        } catch (Exception $e) {
            error_log("Image generation error: " . $e->getMessage());
        }
        return null;
    }
    
    public function send_facebook_message($recipient_id, $message_text) {
        $data = [
            "recipient" => ["id" => $recipient_id],
            "message" => ["text" => $message_text]
        ];
        
        try {
            $response = $this->make_api_request(
                FACEBOOK_GRAPH_API_URL . "?access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN,
                'POST',
                $data
            );
            
            if ($response['http_code'] != 200) {
                error_log("Message send error: " . $response['body']);
            }
        } catch (Exception $e) {
            error_log("Message send exception: " . $e->getMessage());
        }
    }
    
    public function send_facebook_image($recipient_id, $image_url) {
        try {
            $img_response = $this->make_api_request($image_url, 'GET');
            if ($img_response['http_code'] == 200) {
                $image_data = $img_response['body'];
                
                $post_data = [
                    'recipient' => json_encode(["id" => $recipient_id]),
                    'message' => json_encode(["attachment" => ["type" => "image", "payload" => []]]),
                    'access_token' => FACEBOOK_PAGE_ACCESS_TOKEN
                ];
                
                // استخدام multipart form data
                $boundary = uniqid();
                $delimiter = '-------------' . $boundary;
                
                $data = '';
                foreach ($post_data as $name => $content) {
                    $data .= "--" . $delimiter . "\r\n"
                        . "Content-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n"
                        . $content . "\r\n";
                }
                
                $data .= "--" . $delimiter . "\r\n"
                    . "Content-Disposition: form-data; name=\"filedata\"; filename=\"image.jpg\"\r\n"
                    . "Content-Type: image/jpeg\r\n\r\n"
                    . $image_data . "\r\n"
                    . "--" . $delimiter . "--\r\n";
                
                $headers = [
                    "Content-Type: multipart/form-data; boundary=" . $delimiter,
                    "Content-Length: " . strlen($data)
                ];
                
                $response = $this->make_api_request(FACEBOOK_GRAPH_API_URL, 'POST', $data, $headers);
                
                if ($response['http_code'] != 200) {
                    error_log("Image send error: " . $response['body']);
                }
            }
        } catch (Exception $e) {
            error_log("Image send exception: " . $e->getMessage());
        }
    }
    
    public function send_facebook_audio($recipient_id, $audio_bytes) {
        $post_data = [
            'recipient' => json_encode(["id" => $recipient_id]),
            'message' => json_encode(["attachment" => ["type" => "audio", "payload" => []]]),
            'access_token' => FACEBOOK_PAGE_ACCESS_TOKEN
        ];
        
        try {
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;
            
            $data = '';
            foreach ($post_data as $name => $content) {
                $data .= "--" . $delimiter . "\r\n"
                    . "Content-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n"
                    . $content . "\r\n";
            }
            
            $data .= "--" . $delimiter . "\r\n"
                . "Content-Disposition: form-data; name=\"filedata\"; filename=\"audio.mp3\"\r\n"
                . "Content-Type: audio/mpeg\r\n\r\n"
                . $audio_bytes . "\r\n"
                . "--" . $delimiter . "--\r\n";
            
            $headers = [
                "Content-Type: multipart/form-data; boundary=" . $delimiter,
                "Content-Length: " . strlen($data)
            ];
            
            $response = $this->make_api_request(FACEBOOK_GRAPH_API_URL, 'POST', $data, $headers);
            
            if ($response['http_code'] != 200) {
                error_log("Audio send error: " . $response['body']);
            }
        } catch (Exception $e) {
            error_log("Audio send exception: " . $e->getMessage());
        }
    }
    
    public function handle_message($sender_id, $message) {
        echo "📨 Handling message from: " . $sender_id . "\n";
        
        // معالجة الملصقات
        if (isset($message['attachments'])) {
            $attachments = $message['attachments']['data'] ?? [];
            foreach ($attachments as $attachment) {
                // إذا كان ملصق
                if (isset($attachment['type']) && $attachment['type'] == 'sticker') {
                    // لا نرد على الملصقات
                    return;
                }
                
                // معالجة الصور
                $mime_type = strtolower($attachment['mime_type'] ?? '');
                
                if (strpos($mime_type, 'image') !== false) {
                    $image_url = null;
                    if (isset($attachment['image_data']['url'])) {
                        $image_url = $attachment['image_data']['url'];
                    } elseif (isset($attachment['payload']['url'])) {
                        $image_url = $attachment['payload']['url'];
                    } elseif (isset($attachment['url'])) {
                        $image_url = $attachment['url'];
                    }
                    
                    if ($image_url) {
                        $this->send_facebook_message($sender_id, "⏳ جاري تحليل الصورة، الرجاء الانتظار...");
                        $result = $this->process_image($image_url, $sender_id);
                        if ($result) {
                            $this->send_facebook_message($sender_id, $result);
                        } else {
                            $this->send_facebook_message($sender_id, "❌ لم أتمكن من تحليل الصورة.");
                        }
                    }
                    return;
                    
                // معالجة الصوت
                } elseif (strpos($mime_type, 'audio') !== false || 
                         strpos($mime_type, 'voice') !== false || 
                         strpos($mime_type, 'mpeg') !== false) {
                    
                    $audio_url = null;
                    if (isset($attachment['file_url'])) {
                        $audio_url = $attachment['file_url'];
                    } elseif (isset($attachment['payload']['url'])) {
                        $audio_url = $attachment['payload']['url'];
                    } elseif (isset($attachment['url'])) {
                        $audio_url = $attachment['url'];
                    }
                    
                    if ($audio_url) {
                        if (strpos($audio_url, 'facebook.com') !== false && strpos($audio_url, '?') === false) {
                            $audio_url .= "?access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN;
                        }
                        
                        $this->send_facebook_message($sender_id, "⏳ جاري الاستماع 👂...");
                        $this->send_typing_indicator($sender_id, true);
                        $text = $this->transcribe_audio($audio_url);
                        $this->send_typing_indicator($sender_id, false);
                        
                        if ($text) {
                            $this->send_facebook_message($sender_id, "📝 لقد قلت:\n" . $text);
                            
                            $conversation_history = $this->user_conversations[$sender_id] ?? [];
                            $new_messages = array_merge($conversation_history, [["role" => "user", "content" => $text]]);
                            
                            $this->send_typing_indicator($sender_id, true);
                            $response = $this->send_chat_request($new_messages);
                            $this->send_typing_indicator($sender_id, false);
                            
                            if ($response) {
                                $response_message = "خطا من المصدر";
                                foreach ($response['choices'] ?? [] as $choice) {
                                    if (isset($choice['Message']['content'])) {
                                        $response_message = $choice['Message']['content'];
                                        break;
                                    }
                                }
                                $this->send_facebook_message($sender_id, $response_message);
                                
                                $audio_bytes = $this->text_to_speech($response_message, $sender_id);
                                if ($audio_bytes) {
                                    $this->send_facebook_audio($sender_id, $audio_bytes);
                                }
                                
                                $this->user_conversations[$sender_id] = array_merge(
                                    $new_messages, 
                                    [["role" => "assistant", "content" => $response_message]]
                                );
                            }
                        } else {
                            $this->send_facebook_message($sender_id, "❌ لم أتمكن من تحويل الصوت إلى نص.");
                        }
                    }
                    return;
                }
            }
        }
        
        // معالجة الرسائل النصية
        if (isset($message['text']) && is_string($message['text'])) {
            $message_text = $message['text'];
            
            // معالجة سريعة للردود الداخلية
            $message_lower = strtolower($message_text);
            
            if (strpos($message_text, '฿') !== false || 
                strpos($message_text, '👍') !== false || 
                strpos($message_lower, 'جام ثاني') !== false) {
                $this->send_facebook_message($sender_id, "👍");
                return;
            } elseif (strpos($message_text, 'ฯ') !== false || 
                     strpos($message_text, '﷼') !== false) {
                $this->send_facebook_message($sender_id, "أنا بخير، الحمدلله وأنت ");
                return;
            } elseif (strpos($message_lower, "من انت") === 0 || 
                     strpos($message_lower, "من أنت") === 0 || 
                     strpos($message_lower, "من مطورك") === 0 || 
                     strpos($message_lower, "من صانعك") === 0 || 
                     strpos($message_lower, "من صاحبك") === 0) {
                $response = "تم تطويري من قبل مطور بوتات";
                $this->send_facebook_message($sender_id, $response);
                return;
            } elseif (strpos($message_lower, "اسرائيل") !== false || 
                     strpos($message_lower, "إسرائيل") !== false || 
                     strpos($message_lower, 'israel') !== false) {
                $this->send_facebook_message($sender_id, "عذرا انا لا اعرف ما تقول انا اعرف دولة فلسطين 🇵🇸 عاصمتها القدس");
                return;
            }
        }
        
        // إذا كانت الرسالة نصية عادية
        if (isset($message['text']) && $message['text']) {
            $message_text = $message['text'];
            $conversation_history = $this->user_conversations[$sender_id] ?? [];
            $new_messages = array_merge($conversation_history, [["role" => "user", "content" => $message_text]]);

            // إرسال مؤشر الكتابة
            $this->send_typing_indicator($sender_id, true);
            $response = $this->send_chat_request($new_messages);
            $this->send_typing_indicator($sender_id, false);
            
            if ($response) {
                $image_request = false;
                foreach ($response['choices'] ?? [] as $choice) {
                    if (isset($choice['Message']['function_call']['name']) && 
                        $choice['Message']['function_call']['name'] == 'create_ai_art') {
                        try {
                            $args = json_decode($choice['Message']['function_call']['arguments'], true);
                            $prompt = $args['prompt'] ?? '';
                            
                            if ($prompt) {
                                $image_request = true;
                                $this->send_facebook_message($sender_id, "⏳ جاري إنشاء الصور، الرجاء الانتظار...");
                                
                                $this->send_typing_indicator($sender_id, true);
                                
                                // إنشاء 4 صور
                                for ($i = 0; $i < 4; $i++) {
                                    $image_url = $this->generate_images($prompt);
                                    if ($image_url) {
                                        $this->send_facebook_image($sender_id, $image_url);
                                    }
                                }
                                
                                $this->send_typing_indicator($sender_id, false);
                                $this->send_facebook_message($sender_id, "✅ تم إنشاء الصور بنجاح!");
                            }
                        } catch (Exception $e) {
                            error_log("Image generation error: " . $e->getMessage());
                            $this->send_typing_indicator($sender_id, false);
                            $this->send_facebook_message($sender_id, "❌ حدث خطأ أثناء إنشاء الصور");
                        }
                        break;
                    }
                }
                
                if (!$image_request) {
                    $response_message = "عذرًا، حدث خطأ في معالجة طلبك.";
                    foreach ($response['choices'] ?? [] as $choice) {
                        if (isset($choice['Message']['content'])) {
                            $response_message = $choice['Message']['content'];
                            break;
                        }
                    }
                    $this->send_facebook_message($sender_id, $response_message);
                    
                    $audio_bytes = $this->text_to_speech($response_message, $sender_id);
                    if ($audio_bytes) {
                        $this->send_facebook_audio($sender_id, $audio_bytes);
                    }
                    
                    $this->user_conversations[$sender_id] = array_merge(
                        $new_messages, 
                        [["role" => "assistant", "content" => $response_message]]
                    );
                }
            } else {
                $this->send_facebook_message($sender_id, "❌ حدث خطأ في معالجة رسالتك");
            }
        }
    }
    
    public function poll_facebook_messages() {
        echo "🔄 Starting message polling...\n";
        
        while ($this->running) {
            try {
                $url = "https://graph.facebook.com/v11.0/me/conversations?fields=messages.limit(5){message,attachments,from,id}&access_token=" . FACEBOOK_PAGE_ACCESS_TOKEN;
                
                $response = $this->make_api_request($url, 'GET');
                if ($response['http_code'] == 200) {
                    $data = json_decode($response['body'], true);
                    $conversations = $data['data'] ?? [];
                    
                    if (empty($conversations)) {
                        echo "⏳ No new messages...\n";
                    }
                    
                    foreach ($conversations as $conversation) {
                        foreach ($conversation['messages']['data'] as $message) {
                            $msg_id = $message['id'];
                            if (!in_array($msg_id, $this->processed_message_ids)) {
                                $sender_id = $message['from']['id'];
                                $message_content = $message['message'] ?? [];
                                if (is_string($message_content)) {
                                    $message_content = ['text' => $message_content];
                                }
                                
                                if (isset($message['attachments'])) {
                                    $message_content['attachments'] = $message['attachments'];
                                }
                                
                                echo "📨 New message from " . $sender_id . "\n";
                                $this->handle_message($sender_id, $message_content);
                                $this->processed_message_ids[] = $msg_id;
                                
                                // منع تجاوز الذاكرة
                                if (count($this->processed_message_ids) > 1000) {
                                    array_shift($this->processed_message_ids);
                                }
                            }
                        }
                    }
                    
                    $this->wait_seconds(2);
                } else {
                    echo "❌ API Error: " . $response['http_code'] . "\n";
                    $this->wait_seconds(5);
                }
            } catch (Exception $e) {
                echo "❌ Polling error: " . $e->getMessage() . "\n";
                $this->wait_seconds(10);
            }
        }
    }
    
    public function stop_bot() {
        $this->running = false;
        echo "Bot is stopping...\n";
    }
    
    private function make_api_request($url, $method = 'GET', $data = null, $headers = [], $timeout = 30) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        return [
            'body' => $response,
            'http_code' => $http_code
        ];
    }
}

// تنفيذ البوت
function run_bot() {
    $bot = new FacebookBot();
    
    try {
        echo "🚀 Starting Facebook Bot...\n";
        echo "🤖 Bot is now running and monitoring messages...\n";
        echo "📱 Send a message to your Facebook Page to test!\n";
        
        // بدء مهام الخلفية
        $bot->token_refresh_scheduler();
        
    } catch (Exception $e) {
        echo "❌ Fatal error: " . $e->getMessage() . "\n";
        $bot->stop_bot();
    }
}

// إذا كان التشغيل من سطر الأوامر
if (php_sapi_name() === 'cli') {
    run_bot();
}
?>
