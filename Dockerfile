FROM php:5.6-apache

RUN apt-get update && apt-get install -y libldap2-dev wget \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install -j$(nproc) ldap mbstring pdo pdo_mysql \
    && apt-get autoremove -y libldap2-dev \
	&& apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && a2enmod rewrite \
    && wget -O /usr/local/bin/dumb-init --no-verbose https://github.com/Yelp/dumb-init/releases/download/v1.2.2/dumb-init_1.2.2_amd64 \
    && chmod +x /usr/local/bin/dumb-init

RUN echo 'TLS_REQCERT never' >> /etc/ldap/ldap.conf \
    && echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/max.ini \
    && echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/max.ini

# If behind reverse proxy using path (e.g. /renogen), put the path here (without the preceding slash, e.g renogen). 
# Also ensure the reverse proxy retains the path when proxying to this container.
ENV BASE_URL=''

# Timezone using Region/City format
ENV PHP_TIMEZONE=Asia/Kuala_Lumpur

# Database info
ENV DB_HOST=localhost
ENV DB_PORT=3306
ENV DB_NAME=renogen
ENV DB_USER=renogen
ENV DB_PASSWORD=reno123gen

# To support login using LDAP. For LDAPS, use 'ldaps://host:port'
ENV LDAP_HOST=''
ENV LDAP_PORT=389
ENV LDAP_DN="uid={username},ou=users,o=company"

HEALTHCHECK CMD sleep 10 && curl -sSf http://localhost/healthcheck.php || exit 1

ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]

CMD bash -c './init_renogen.sh && apache2-foreground'

COPY . /tmp/src/

RUN mv /tmp/src/* /var/www/html/ \
    && mv /tmp/src/.htaccess /var/www/html/ \
    && chown -R www-data:www-data /var/www
    && chmod +x ./init_renogen.sh
