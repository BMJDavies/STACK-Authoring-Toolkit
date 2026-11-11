# stackapi.sh: start and probe the standalone STACK API (tools/stackapi).
#
# Sourced by api-check.sh and render-sweep.sh after they set HERE (the tools/
# folder). Provides:
#   api_up        the API answers at $STACKAPI_URL
#   api_possible  this machine can run the API's images at all
#   start_api     bring the containers up and wait for the API to answer

STACKAPI_URL="${STACKAPI_URL:-http://localhost:3080}"
STACKAPI_COMPOSE="$HERE/stackapi/docker-compose.yml"

api_up() {
    curl -s -o /dev/null -m 3 "$STACKAPI_URL/render" -X POST \
        -H 'Content-Type: application/json' \
        -d '{"questionDefinition":"<quiz><question type=\"stack\"></question></quiz>"}'
}

# The goemaxima and stackapi images are amd64-only: native on Intel/AMD, under
# Rosetta on Apple silicon. Linux on ARM (a Raspberry Pi, an ARM server) cannot
# run them, and Docker restarts them in a loop instead of failing.
api_possible() {
    case "$(uname -s)/$(uname -m)" in
        */x86_64|*/amd64|Darwin/arm64) return 0 ;;
        *) return 1 ;;
    esac
}

start_api() {
    echo "Starting stackapi containers..."
    docker compose -f "$STACKAPI_COMPOSE" -p stackapi up -d >/dev/null || return 1
    local i
    for i in $(seq 1 30); do
        api_up && return 0
        sleep 2
    done
    return 1
}
