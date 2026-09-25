# Runs the CineVault site (static front end + PHP backend) as a single
# container for Render's free web service tier.
#
# Uses PHP's built-in server rather than Apache/nginx — this project is a
# small evaluation build with light traffic, so the built-in server is
# simple, has zero extra config, and needs no separate web-server setup.

FROM php:8.3-cli

# mbstring (used by php/contact.php for mb_strlen) and curl/openssl (used by
# PHPMailer for the Gmail SMTP connection) aren't enabled by default.
RUN apt-get update && apt-get install -y --no-install-recommends \
      libcurl4-openssl-dev \
      libonig-dev \
    && docker-php-ext-install curl mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Render injects the real port to bind to via $PORT at runtime; 10000 is
# just a sensible local default if this is ever run without it set.
EXPOSE 10000
CMD php -S 0.0.0.0:${PORT:-10000} -t /app
