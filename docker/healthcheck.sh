#!/bin/sh
set -e

case "${SERVICE_TYPE:-web}" in
    web)
        curl --fail --silent --show-error --max-time 5 http://127.0.0.1/up >/dev/null
        ;;
    reverb)
        tr '\000' ' ' </proc/1/cmdline | grep -q 'artisan reverb:start'
        php -r 'exit(@fsockopen("127.0.0.1", 8080, $errorCode, $errorMessage, 5) ? 0 : 1);'
        ;;
    worker)
        tr '\000' ' ' </proc/1/cmdline | grep -q 'artisan horizon'
        php artisan horizon:status >/dev/null
        ;;
    scheduler)
        find /tmp/scheduler-heartbeat -mmin -3 -print -quit | grep -q .
        ;;
    *)
        echo "Unknown SERVICE_TYPE: ${SERVICE_TYPE:-unset}" >&2
        exit 1
        ;;
esac
