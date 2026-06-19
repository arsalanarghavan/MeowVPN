FROM php:8.3-cli-alpine
WORKDIR /app
RUN apk add --no-cache git unzip curl \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY backend/packages/svp-bot-core /packages/svp-bot-core
COPY bale_bot /app
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8092
ENV PORT=8092
CMD ["php", "-S", "0.0.0.0:8092", "-t", "public"]
