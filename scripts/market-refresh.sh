#!/bin/sh
set -eu
# One local cron entry fetches cached market data independently of visitor traffic.
CHANDI_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
CHANDI_PHP=${CHANDI_PHP:-/Applications/XAMPP/xamppfiles/bin/php}
cd "$CHANDI_ROOT"
"$CHANDI_PHP" artisan market:fetch
"$CHANDI_PHP" artisan market:references
"$CHANDI_PHP" artisan queue:work --stop-when-empty --max-time=40 --tries=3
