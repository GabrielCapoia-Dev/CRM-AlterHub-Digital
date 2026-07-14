#!/bin/sh
set -eu

# Runtime containers must never install dependencies or mutate application
# state. Deployments run migrations and seeders as explicit one-off commands.
if [ "${APP_ENV:-production}" = "production" ]; then
    if [ "${APP_DEBUG:-false}" != "false" ]; then
        echo "Refusing to start production with APP_DEBUG enabled." >&2
        exit 1
    fi

    if ! php -r '
        $key = getenv("APP_KEY") ?: "";
        $decoded = str_starts_with($key, "base64:")
            ? base64_decode(substr($key, 7), true)
            : $key;
        exit(is_string($decoded) && strlen($decoded) === 32 ? 0 : 1);
    '; then
        echo "APP_KEY must decode to exactly 32 bytes before production starts." >&2
        exit 1
    fi
fi

exec "$@"
