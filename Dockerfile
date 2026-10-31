# PHP 8.4.27 security release.
FROM ghcr.io/shanginn/spiral-docker-image-base:php-8.4.27-amd64@sha256:57da4b8e8fd5c8b8e7d07354402a9bf7243a79b33816588473f34ccc5ac13fff

RUN php -r 'if (PHP_VERSION !== "8.4.27") { throw new RuntimeException("PHP 8.4.27 is required"); }'

WORKDIR /app
VOLUME /app

COPY --chown=som:som composer.json composer.lock /app/

RUN set -xe && \
    composer install \
        --prefer-dist --no-scripts \
        --no-progress --no-interaction

RUN chown som:som /app /opt

COPY --chown=som:som . /app

USER som

ENTRYPOINT ["php", "src/bot.php"]
