FROM php:8.2-cli
# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl
# Crear carpeta de la app
WORKDIR /app
# Copiar todo
COPY . .
# Exponer puerto para Render
EXPOSE 10000
# Comando para iniciar servidor PHP
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]
