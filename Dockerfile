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
    mkdir -p /var/www/html/public /var/www/html/public/js /var/www/html/css /var/www/html/api /var/www/html/config /var/www/html/core /var/www/html/database; \
    for file in /var/www/html/*.html; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/public/; \
    done; \
    for file in /var/www/html/*.css; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/css/; \
    done; \
    for file in /var/www/html/api-helper.js /var/www/html/MAIN.JS /var/www/html/main.js; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/public/js/; \
    done; \
    if [ -f /var/www/html/MAIN.JS ]; then cp -f /var/www/html/MAIN.JS /var/www/html/public/js/main.js; fi; \
    for file in /var/www/html/add-candidates.php /var/www/html/admin-candidate-registrations.php /var/www/html/admin-candidates.php /var/www/html/admin.php /var/www/html/auth.php /var/www/html/candidate-auth.php /var/www/html/campaign-management.php /var/www/html/candidates.php /var/www/html/check-admin.php /var/www/html/check-candidates-schema.php /var/www/html/create-admin.php /var/www/html/direct-test.php /var/www/html/election.php /var/www/html/get-voters.php /var/www/html/mpesa-callback.php /var/www/html/test-candidates.php /var/www/html/test-login.php /var/www/html/test-register.php /var/www/html/test.php /var/www/html/vote.php; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/api/; \
    done; \
    for file in /var/www/html/database.php /var/www/html/admin_database.php /var/www/html/env.php /var/www/html/mpesa.php /var/www/html/database.example.php /var/www/html/admin_database.example.php /var/www/html/mpesa.example.php; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/config/; \
    done; \
    for file in /var/www/html/AdminAuth.php /var/www/html/Blockchain.php /var/www/html/Cryptography.php /var/www/html/MpesaService.php /var/www/html/SystemSettings.php /var/www/html/Validator.php; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/core/; \
    done; \
    for file in /var/www/html/schema.sql /var/www/html/admin_schema.sql /var/www/html/create-campaign-management-tables.sql /var/www/html/create-candidate-registration-table.sql /var/www/html/create-payment-table.sql /var/www/html/candidates-schema.sql; do \
        [ -f "$file" ] || continue; \
        cp -f "$file" /var/www/html/database/; \
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
