#!/usr/bin/env bash
# ==============================================================================
# N.A Fresh Fruits & Coconuts - 30-Second Telegram Queue Cron Runner
# Executes twice per minute (at 00s and 30s) from a standard 1-minute crontab
# ==============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PHP_BIN="$(which php || echo "/usr/bin/php")"
LOG_FILE="$PROJECT_ROOT/storage/logs/telegram_cron.log"

# Pass 1: Run immediately at start of minute
"$PHP_BIN" "$PROJECT_ROOT/bin/cron_telegram_queue.php" >> "$LOG_FILE" 2>&1

# Wait 30 seconds
sleep 30

# Pass 2: Run at 30 seconds
"$PHP_BIN" "$PROJECT_ROOT/bin/cron_telegram_queue.php" >> "$LOG_FILE" 2>&1
