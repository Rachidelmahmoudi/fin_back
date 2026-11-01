#!/bin/sh
# Sauvegarde quotidienne : crontab -e  =>  0 3 * * * /opt/finpharma/deploy/backup.sh
set -e
DIR=/opt/finpharma/backups; mkdir -p "$DIR"
cd /opt/finpharma/deploy
docker compose -f compose.prod.yaml exec -T db pg_dump -U finpharma finpharma | gzip > "$DIR/finpharma-$(date +%F).sql.gz"
find "$DIR" -name '*.sql.gz' -mtime +14 -delete
