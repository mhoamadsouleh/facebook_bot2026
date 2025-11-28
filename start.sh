#!/bin/bash

echo "🚀 Starting Facebook Bot..."

# تشغيل ويب هوك في الخلفية
php -S 0.0.0.0:8000 index.php &

# تشغيل البوت الرئيسي
php facebook_bot.php