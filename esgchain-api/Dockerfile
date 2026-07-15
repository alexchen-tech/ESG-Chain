# Stage 1: 建立完整 Laravel 專案 + 安裝相依套件
FROM composer:latest AS builder

WORKDIR /app

# 建立 Laravel 骨架
RUN composer create-project laravel/laravel . --prefer-dist --no-interaction

# 安裝額外套件（在 Laravel 基礎上加入 jwt-auth, spatie 等）
RUN composer require \
    php-open-source-saver/jwt-auth:^2.4 \
    spatie/laravel-permission:^6.0 \
    guzzlehttp/guzzle:^7.8 \
    league/csv:^9.0 \
    --no-interaction --no-scripts --optimize-autoloader

# 複製我們的自訂檔案（覆蓋 Laravel 預設）
COPY app/ ./app/
COPY config/ ./config/
COPY database/ ./database/
COPY routes/ ./routes/
COPY bootstrap/ ./bootstrap/

# 複製 Spatie permission config（migration 已在 database/migrations/ 中自訂）
RUN cp vendor/spatie/laravel-permission/config/permission.php config/permission.php

# 修補 config/jwt.php：讓金鑰值支援讀取檔案路徑
RUN sed -i "s/'public' => env('JWT_PUBLIC_KEY')/'public' => (env('JWT_PUBLIC_KEY') \&\& file_exists(env('JWT_PUBLIC_KEY')) ? file_get_contents(env('JWT_PUBLIC_KEY')) : env('JWT_PUBLIC_KEY'))/g" config/jwt.php && \
    sed -i "s/'private' => env('JWT_PRIVATE_KEY')/'private' => (env('JWT_PRIVATE_KEY') \&\& file_exists(env('JWT_PRIVATE_KEY')) ? file_get_contents(env('JWT_PRIVATE_KEY')) : env('JWT_PRIVATE_KEY'))/g" config/jwt.php && \
    sed -i "s/'algo' => env('JWT_ALGO', 'HS256')/'algo' => env('JWT_ALGO', 'RS256')/g" config/jwt.php

# Stage 2: 輕量 PHP runtime
FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    autoconf g++ make \
    git curl zip unzip \
    libpng-dev libjpeg-turbo-dev \
    oniguruma-dev libxml2-dev \
    mysql-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

WORKDIR /app

# 從 builder 複製完整專案（含 vendor 和 artisan）
COPY --from=builder /app .

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
# 啟動時先 cache config（讓 jwt.php 中的 file_get_contents 在 config:cache 時執行）
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan serve --host=0.0.0.0 --port=8080"]
