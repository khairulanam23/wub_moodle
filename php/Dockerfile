FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libpq-dev \
    libmariadb-dev \
    libcurl4-openssl-dev \
    libxslt1-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mysqli \
        opcache \
        zip \
        soap \
        exif \
        mbstring \
        xml \
        xsl \
        curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
