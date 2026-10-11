FROM php:8.3-apache AS schema

# El esquema canónico incluye migraciones y datos de demostración.
# Generar una instalación limpia que utilice MYSQL_DATABASE, sin datos de ejemplo.
COPY database/schema.sql /tmp/schema.sql
COPY docker/prepare-schema.php /tmp/prepare-schema.php
RUN php /tmp/prepare-schema.php /tmp/schema.sql /tmp/01-schema.sql

FROM mysql:8.4
COPY --from=schema /tmp/01-schema.sql /docker-entrypoint-initdb.d/01-schema.sql
