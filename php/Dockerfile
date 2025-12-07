FROM php:8.2-fpm-alpine

# Установка расширений
RUN docker-php-ext-install pdo pdo_mysql

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www

# Копируем composer files
COPY composer.json composer.lock* ./

# Устанавливаем зависимости (если composer.lock существует)
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --optimize-autoloader; \
    fi

# Копируем код приложения
COPY . .

# Создаем директории для storage
RUN mkdir -p /var/www/storage/uploads /var/www/storage/processing /var/www/storage/results && \
    chown -R www-data:www-data /var/www/storage

EXPOSE 9000

CMD ["php-fpm"]
