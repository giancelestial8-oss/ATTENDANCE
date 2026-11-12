FROM dunglas/frankenphp:php8.3

RUN install-php-extensions mysqli pdo_mysql

COPY . /app/public

WORKDIR /app/public
