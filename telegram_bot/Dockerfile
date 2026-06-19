FROM php:8.3-cli-alpine
WORKDIR /app
RUN apk add --no-cache git unzip curl \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY backend/packages/svp-bot-core /packages/svp-bot-core
COPY telegram_bot /app
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8091
ENV PORT=8091
CMD ["php", "-S", "0.0.0.0:8091", "-t", "public"]
