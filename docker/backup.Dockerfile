# syntax=docker/dockerfile:1

# El cliente pg_dump debe ser de la misma versión mayor que el servidor.
FROM postgres:17-bookworm

COPY docker/backup.sh /usr/local/bin/backup.sh

RUN sed -i 's/\r$//' /usr/local/bin/backup.sh \
    && chmod +x /usr/local/bin/backup.sh

ENTRYPOINT ["/usr/local/bin/backup.sh"]
