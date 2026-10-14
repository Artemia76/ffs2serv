# Image de développement / test du portail FFS2play : PHP + Apache + pdo_mysql
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/ffs2play.conf \
    && a2enconf ffs2play
WORKDIR /var/www/html
