# Usar a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Atualizar pacotes e instalar Python 3, pip e dependências de compressão
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP necessárias para o MySQL
RUN docker-php-ext-install pdo_mysql

# Instalar bibliotecas Python necessárias para processar PDF e Excel
# Usamos --break-system-packages pois estamos em um container descartável e isolado
RUN pip install --break-system-packages openpyxl pypdf --no-cache-dir

# Habilitar o módulo mod_rewrite do Apache (caso precise de rotas amigáveis)
RUN a2enmod rewrite

# Ajustar os limites de upload do PHP (importante para planilhas/PDFs grandes)
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copiar todo o código fonte para a pasta pública do Apache
COPY . /var/www/html/

# Ajustar as permissões de arquivos para o usuário do Apache
RUN chown -R www-data:www-data /var/www/html

# Configurar o Apache para escutar na porta interna 3020
RUN sed -i 's/80/3020/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Expor a porta configurada do Apache
EXPOSE 3020
