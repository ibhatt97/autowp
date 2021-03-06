#!/bin/sh

set -e

echo "Create dirs"

mkdir -p /var/run/php
mkdir -p /run/php
mkdir -p /run/nginx

mkdir -p /var/log/nginx 
mkdir -p /var/log/php7
mkdir -p /var/log/supervisor
mkdir -p /app/logs && chmod 0777 /app/logs

mkdir -p /app/public_html/img

echo "Waiting for mysql"

waitforit -host=$AUTOWP_DB_HOST -port=$AUTOWP_DB_PORT -timeout=60

echo "Starting supervisor"

/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
