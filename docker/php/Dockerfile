FROM php:8.2-fpm

# Install system dependencies
# tesseract-ocr (+ por/eng) é necessário para o job ProcessarOcrAnexo (TesseractOCR->lang('por','eng')).
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libonig-dev libxml2-dev \
    libjpeg-dev libpng-dev libfreetype6-dev libicu-dev \
    tesseract-ocr tesseract-ocr-por tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*

# GD and common PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mbstring pdo_mysql zip xml intl opcache

# Redis (phpredis) para fila/cache/sessão
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Optional: Configure opcache for production
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=1"; \
  echo "opcache.validate_timestamps=0"; \
  echo "opcache.max_accelerated_files=20000"; \
  echo "opcache.memory_consumption=128"; \
  echo "opcache.interned_strings_buffer=16"; \
} > /usr/local/etc/php/conf.d/opcache.ini