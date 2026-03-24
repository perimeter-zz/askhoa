#!/bin/bash
# storage_cleanup.sh — runs via Hostinger cron
# Deletes session JSON files older than 3 days, then trims to 10MB if needed.

STORAGE="/home/u789657666/domains/askhoa.org/storage"
MAX_BYTES=$((10 * 1024 * 1024))  # 10MB

# Step 1: delete files older than 3 days (skip non-json files like readme)
find "$STORAGE" -name "askhoa_*.json" -mtime +3 -delete

# Step 2: if still over 10MB, delete oldest files first until under limit
while true; do
    size=$(du -sb "$STORAGE" | awk '{print $1}')
    if [ "$size" -le "$MAX_BYTES" ]; then
        break
    fi
    oldest=$(find "$STORAGE" -name "askhoa_*.json" -printf '%T+ %p\n' | sort | head -1 | awk '{print $2}')
    if [ -z "$oldest" ]; then
        break
    fi
    rm -f "$oldest"
done
