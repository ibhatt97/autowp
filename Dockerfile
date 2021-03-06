FROM ubuntu:bionic

LABEL maintainer "dmitry@pereslegin.ru"

WORKDIR /app

EXPOSE 80

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV WAITFORIT_VERSION="v2.2.0"

RUN DEBIAN_FRONTEND=noninteractive apt-get autoremove -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get update -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get dist-upgrade -qq -y && \
    DEBIAN_FRONTEND=noninteractive apt-get install -qq -y \
        autoconf \
        automake \
        bash \
        build-essential \
        ca-certificates \
        curl \
        git \
        golang \
        imagemagick \
        libpng-dev \
        libtool \
        libxml2 \
        logrotate \
        nasm \
        nginx \
        openssh-client \
        optipng \
        php \
        php-ctype \
        php-curl \
        php-dom \
        php-exif \
        php-fileinfo \
        php-fpm \
        php-ftp \
        php-iconv \
        php-imagick \
        php-intl \
        php-json \
        php-gd \
        php-mbstring \
        php-memcached \
        php-mysql \
        php-opcache \
        php-pdo \
        php-phar \
        php-simplexml \
        php-tokenizer \
        php-xml \
        php-xmlwriter \
        php-zip \
        pngquant \
        rsyslog \
        ssmtp \
        supervisor \
        tzdata

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash - && \
    apt-get install -qq -y nodejs

RUN DEBIAN_FRONTEND=noninteractive apt-get autoclean -qq -y

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --quiet && \
    rm composer-setup.php

RUN curl -o /usr/local/bin/waitforit -sSL https://github.com/maxcnunes/waitforit/releases/download/$WAITFORIT_VERSION/waitforit-linux_amd64 && \
    chmod +x /usr/local/bin/waitforit

RUN go get \
        github.com/gin-gonic/gin \
        github.com/go-sql-driver/mysql \
        github.com/Masterminds/squirrel \
    && echo $GOROOT \
    && echo $GOPATH

COPY ./etc/ /etc/

COPY composer.json /app/composer.json
RUN php ./composer.phar install --no-dev --no-progress --no-interaction --no-suggest --optimize-autoloader && \
    php ./composer.phar clearcache

COPY package.json /app/package.json

RUN npm install -y -qq --production && \
    npm cache clean --force

COPY . /app

RUN chmod +x zf && \
    chmod +x start.sh && \
    crontab ./crontab && \
    go build -o ./goautowp/goautowp ./goautowp/

RUN ./node_modules/.bin/webpack -p

RUN rm -rf ./node_modules/

CMD ["./start.sh"]
