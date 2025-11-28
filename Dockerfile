FROM php:8.1-cli

# تثبيت الإضافات المطلوبة
RUN apt-get update && apt-get install -y \
    curl \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    git \
    unzip \
    && docker-php-ext-install curl \
    && docker-php-ext-install sockets

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# إنشاء مجلد العمل
WORKDIR /app

# نسخ ملفات المشروع
COPY . .

# تثبيت الاعتماديات (إذا كان هناك composer.json)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# تعيين الصلاحيات
RUN chmod +x /app/start.sh

# البورت الذي سيعمل عليه التطبيق
EXPOSE 8000

# أمر التشغيل
CMD ["/app/start.sh"]