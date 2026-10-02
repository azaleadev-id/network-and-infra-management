<?php
class Notification {
    public static function sendTelegram($chat_id, $msg, $token) {
        file_get_contents("https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=".urlencode($msg));
    }
    // ...add more methods for email, WhatsApp, etc...
}
