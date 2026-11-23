# Uso una imagen que ya incluye PHP 8.3 y el servidor web Apache.
FROM php:8.3-apache

# Instalo las bibliotecas de internacionalización y las herramientas para archivos ZIP.
# intl es un requisito de Filament. pdo_mysql y zip sirven para MySQL y los archivos ZIP.
# Al terminar, borro la lista descargada para que la imagen ocupe menos espacio.
RUN apt-get update \
    && apt-get install -y libicu-dev libzip-dev unzip \
    && docker-php-ext-install intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Copio Composer desde su imagen oficial para poder instalar las dependencias de Laravel.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establezco la carpeta de trabajo dentro del contenedor.
WORKDIR /var/www/html

# Guardo en esta variable la carpeta pública de Laravel, que Apache debe servir.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Cambio las rutas de la configuración de Apache para que utilice la carpeta public.
# Así, la entrada de la web es public/index.php y las demás carpetas quedan fuera del acceso web directo.
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Activo el módulo de Apache que permite usar las reglas de public/.htaccess.
# Estas reglas permiten que Laravel reciba las peticiones a rutas como /categoria/quesos.
RUN a2enmod rewrite
