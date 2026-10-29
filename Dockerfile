FROM php:8.2-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,target=/run/proxy-ca,required=false \
    if [ -f /run/proxy-ca ]; then export SSL_CERT_FILE=/run/proxy-ca; fi; \
    apt-get -o Acquire::https::CaInfo="${SSL_CERT_FILE:-/etc/ssl/certs/ca-certificates.crt}" update && apt-get install -y --no-install-recommends unzip git && rm -rf /var/lib/apt/lists/* && docker-php-ext-install pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
