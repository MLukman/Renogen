FROM php:5.6-apache

RUN apt-get update && apt-get install -y libldap2-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install -j$(nproc) ldap \
    && apt-get autoremove -y libldap2-dev \
	&& apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && a2enmod rewrite    

COPY . /tmp/src/

RUN echo 'date.timezone = Asia/Kuala_Lumpur' > /usr/local/etc/php/conf.d/timezone.ini \
    && echo 'upload_max_filesize = 100M' > /usr/local/etc/php/conf.d/max.ini \
    && echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/max.ini \
	&& mv /tmp/src/* /var/www/html/ \
    && mv /tmp/src/.htaccess /var/www/html/ \
    && mkdir /data \
    && rm -fr /var/www/html/data \
    && ln -s /data /var/www/html/data \
    && chown -R www-data:www-data /var/www \
    && chown -R www-data:www-data /data

VOLUME ["/data"]