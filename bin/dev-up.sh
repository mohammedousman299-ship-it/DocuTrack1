#!/bin/sh
# Relance les services de développement après un redémarrage de la machine ou
# du conteneur. Idempotent : sans effet si tout tourne déjà.
#
# PostgreSQL et Docker ne survivent pas à un redémarrage : sans ce script, la
# suite de tests échoue sur une erreur de connexion qui ressemble à une panne
# applicative alors qu'il ne manque qu'un service.
set -e

echo "PostgreSQL…"
pg_ctlcluster 16 main start 2>/dev/null || true
if pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1; then
    echo "  prêt"
else
    echo "  indisponible — vérifiez l'installation locale"
fi

echo "Stockage objet (MinIO)…"
if ! docker info >/dev/null 2>&1; then
    dockerd >/tmp/dockerd.log 2>&1 &
    sleep 10
fi
docker compose up -d storage storage-init >/dev/null 2>&1 || true
if curl -sf -o /dev/null "http://127.0.0.1:9000/minio/health/live"; then
    echo "  prêt"
else
    echo "  indisponible — voir docker compose logs storage"
fi
