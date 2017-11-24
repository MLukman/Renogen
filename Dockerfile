FROM php:5.6-apache

RUN apt-get update && apt-get install -y libldap2-dev wget \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install -j$(nproc) ldap \
    && apt-get autoremove -y libldap2-dev \
	&& apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && echo 'date.timezone = Asia/Kuala_Lumpur' > /usr/local/etc/php/conf.d/timezone.ini \
    && echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/max.ini \
    && echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/max.ini \
    && a2enmod rewrite \
	&& wget -O /usr/local/bin/dumb-init --no-verbose https://github.com/Yelp/dumb-init/releases/download/v1.2.0/dumb-init_1.2.0_amd64 \
	&& chmod +x /usr/local/bin/dumb-init

COPY . /tmp/src/

RUN mv /tmp/src/* /var/www/html/ \
    && mv /tmp/src/.htaccess /var/www/html/ \
    && mkdir /data \
    && rm -fr /var/www/html/data \
    && ln -s /data /var/www/html/data \
    && chown -R www-data:www-data /var/www
    
HEALTHCHECK CMD sleep 10 && curl -sSf http://localhost/healthcheck.php || exit 1

VOLUME ["/data"]

ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]

CMD ["bash", "-c", "chown -R www-data:www-data /data && exec apache2-foreground"]