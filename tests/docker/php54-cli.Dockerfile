FROM php:5.4-cli@sha256:d9eaac6344eb9399f053ef6b538a237101d686d866fb484ad4834bfada6afbd9

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libxml2-dev \
        zlib1g-dev; \
    docker-php-ext-install mbstring xml xmlwriter tokenizer; \
    rm -rf /var/lib/apt/lists/*
