FROM node:22-bookworm-slim@sha256:de170b39439eb0e5a3444ad7d60b015614a381e10fecae2e30ec9e735122f38d AS css
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY tailwind.config.js tema.js *.php ./
COPY assets/ ./assets/
RUN npm run build:css

FROM php:8.4-apache-bookworm@sha256:e653ff90d7f6e2e6f81eddb07c864f1eae224ccbf5143f82a44f25e2f110e320
RUN apt-get update && apt-get install -y --no-install-recommends python3 python3-venv libonig-dev libcurl4-openssl-dev ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring curl opcache \
    && rm -rf /var/lib/apt/lists/*
RUN python3 -m venv /opt/venv
COPY requirements.txt /opt/sga/requirements.txt
RUN /opt/venv/bin/pip install --no-cache-dir -r /opt/sga/requirements.txt
COPY import_excel.py /opt/sga/import_excel.py
ENV PYTHON_BIN=/opt/venv/bin/python IMPORT_EXCEL_SCRIPT=/opt/sga/import_excel.py
COPY *.php .htaccess /var/www/html/
COPY scripts/ /var/www/html/scripts/
COPY assets/ /var/www/html/assets/
COPY --from=css /build/assets/tailwind.css /var/www/html/assets/tailwind.css
COPY tema.js /var/www/html/tema.js
COPY docker/php.ini /usr/local/etc/php/conf.d/sga.ini
COPY docker/apache.conf /etc/apache2/conf-available/sga.conf
RUN mkdir -p /var/lib/php/sessions && chown www-data:www-data /var/lib/php/sessions && chmod 700 /var/lib/php/sessions
RUN a2enmod rewrite headers && a2enconf sga \
    && sed -i 's/80/3020/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf \
    && chown -R root:root /var/www/html /opt/sga && chmod -R a-w /var/www/html /opt/sga
EXPOSE 3020
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD php -r 'exit(@file_get_contents("http://127.0.0.1:3020/health.php") === false ? 1 : 0);'
