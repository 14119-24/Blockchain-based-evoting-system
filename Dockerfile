FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql curl \
    && a2enmod rewrite headers expires \
    && sed -i 's/Listen 80/Listen 10000/g' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN set -eux; \
    printf '%s\n' \
    '<VirtualHost *:10000>' \
    '    ServerName localhost' \
    '    DocumentRoot /var/www/html/public' \
    '    DirectoryIndex index.html index.php' \
    '' \
    '    ErrorLog /proc/self/fd/2' \
    '    CustomLog /proc/self/fd/1 combined' \
    '' \
    '    SetEnvIf X-Forwarded-Proto https HTTPS=on' \
    '' \
    '    <Directory /var/www/html/public>' \
    '        Options FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '' \
    '    Alias /public/ /var/www/html/public/' \
    '' \
    '    Alias /api/ /var/www/html/api/' \
    '    <Directory /var/www/html/api>' \
    '        Options FollowSymLinks' \
    '        AllowOverride None' \
    '        Require all granted' \
    '        DirectoryIndex index.php' \
    '    </Directory>' \
    '' \
    '    Alias /css/ /var/www/html/css/' \
    '    <Directory /var/www/html/css>' \
    '        Options FollowSymLinks' \
    '        AllowOverride None' \
    '        Require all granted' \
    '' \
    '        <FilesMatch "\.php$">' \
    '            Require all denied' \
    '        </FilesMatch>' \
    '    </Directory>' \
    '' \
    '    Alias /setup-api.php /var/www/html/setup-api.php' \
    '' \
    '    <LocationMatch "^/(config|core|database|docs|tests|\.vscode)(/|$)">' \
    '        Require all denied' \
    '    </LocationMatch>' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf; \
    mkdir -p /var/www/html/public /var/www/html/public/js /var/www/html/css /var/www/html/api; \
    for file in /var/www/html/*.html; do \
        [ -f "$file" ] || continue; \
        base="$(basename "$file")"; \
        [ -e "/var/www/html/public/$base" ] || ln -sf "../$base" "/var/www/html/public/$base"; \
    done; \
    for file in /var/www/html/*.css; do \
        [ -f "$file" ] || continue; \
        base="$(basename "$file")"; \
        [ -e "/var/www/html/css/$base" ] || ln -sf "../$base" "/var/www/html/css/$base"; \
    done; \
    for name in api-helper.js MAIN.JS main.js; do \
        if [ -f "/var/www/html/$name" ] && [ ! -e "/var/www/html/public/js/$name" ]; then \
            ln -sf "../../$name" "/var/www/html/public/js/$name"; \
        fi; \
    done; \
    if [ ! -e /var/www/html/public/js/main.js ] && [ -f /var/www/html/MAIN.JS ]; then \
        ln -sf "../../MAIN.JS" /var/www/html/public/js/main.js; \
    fi; \
    for name in add-candidates.php admin-candidate-registrations.php admin-candidates.php admin.php auth.php candidate-auth.php campaign-management.php candidates.php check-admin.php check-candidates-schema.php create-admin.php direct-test.php election.php get-voters.php mpesa-callback.php test-candidates.php test-login.php test-register.php test.php vote.php; do \
        if [ -f "/var/www/html/$name" ] && [ ! -e "/var/www/html/api/$name" ]; then \
            ln -sf "../$name" "/var/www/html/api/$name"; \
        fi; \
    done; \
    if [ ! -e /var/www/html/public/.htaccess ]; then \
        printf '%s\n' \
        'Options -Indexes' \
        '' \
        '<IfModule mod_rewrite.c>' \
        '    RewriteEngine On' \
        '' \
        '    RewriteCond %{REQUEST_FILENAME} -f [OR]' \
        '    RewriteCond %{REQUEST_FILENAME} -d' \
        '    RewriteRule ^ - [L]' \
        '' \
        '    RewriteCond %{REQUEST_FILENAME}.html -f' \
        '    RewriteRule ^([^./]+)$ $1.html [L]' \
        '</IfModule>' \
        > /var/www/html/public/.htaccess; \
    fi; \
    chown -R www-data:www-data /var/www/html

EXPOSE 10000

