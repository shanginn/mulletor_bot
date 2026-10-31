# PHP 8.4.27 security release.
FROM ghcr.io/shanginn/spiral-docker-image-base:master@sha256:335f1d810def4203cebffbf1e9ca8045537f13d1d262abf8d45f4770459073ad

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
