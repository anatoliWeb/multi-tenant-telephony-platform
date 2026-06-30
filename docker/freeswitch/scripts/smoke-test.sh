#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../../.." && pwd)"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"
BROWSER_SIP_DOMAIN="${FREESWITCH_SIP_DOMAIN:-localhost}"
BROWSER_WSS_URL="${FREESWITCH_SIP_WSS_URL:-${FREESWITCH_WEBRTC_WSS_URL:-}}"

if [ -z "$BROWSER_WSS_URL" ]; then
  BROWSER_WSS_URL="wss://${BROWSER_SIP_DOMAIN}:7443"
fi

run_fs_cli() {
  docker compose -f "$COMPOSE_FILE" exec -T freeswitch fs_cli -x "$1"
}

resolve_directory_domain() {
  if [ -n "${FREESWITCH_DIRECTORY_DOMAIN:-}" ]; then
    printf '%s\n' "$FREESWITCH_DIRECTORY_DOMAIN"
    return 0
  fi

  runtime_domain="$(run_fs_cli "global_getvar local_ip_v4" 2>/dev/null | tr -d '\r' | awk 'NF { value = $0 } END { print value }')"

  if [ -n "$runtime_domain" ]; then
    printf '%s\n' "$runtime_domain"
    return 0
  fi

  printf '%s\n' localhost
}

if [ -z "$(docker compose -f "$COMPOSE_FILE" ps -q freeswitch)" ]; then
  echo "FreeSWITCH must be running before smoke testing." >&2
  echo "Start it with: docker compose --profile freeswitch up -d freeswitch" >&2
  exit 1
fi

DIRECTORY_DOMAIN="$(resolve_directory_domain)"

echo "FreeSWITCH status:"
run_fs_cli "status"

echo "FreeSWITCH internal profile status:"
INTERNAL_PROFILE_STATUS="$(run_fs_cli "sofia status profile internal")"
printf '%s\n' "$INTERNAL_PROFILE_STATUS"

echo "Browser SIP domain: $BROWSER_SIP_DOMAIN"
echo "Browser SIP WSS URL: $BROWSER_WSS_URL"
echo "FreeSWITCH directory lookup domain: $DIRECTORY_DOMAIN"

# Live smoke tests are intentionally separate from the default Laravel suite.
# They validate the optional PBX container and local demo provisioning only.
"$SCRIPT_DIR/provision-demo-users.sh"

if ! printf '%s\n' "$INTERNAL_PROFILE_STATUS" | grep -q 'WS-BIND-URL'; then
  echo "FreeSWITCH internal profile is missing WS-BIND-URL." >&2
  exit 1
fi

if ! printf '%s\n' "$INTERNAL_PROFILE_STATUS" | grep -q 'WSS-BIND-URL'; then
  echo "FreeSWITCH internal profile is missing WSS-BIND-URL." >&2
  exit 1
fi

for user in 1001 1002; do
  if [ "$(run_fs_cli "user_exists id $user $DIRECTORY_DOMAIN" | tr -d '\r' | awk 'NF { value = $0 } END { print value }')" != "true" ]; then
    echo "FreeSWITCH smoke test could not resolve demo user $user in domain $DIRECTORY_DOMAIN." >&2
    exit 1
  fi

  echo "Verified demo user $user in domain $DIRECTORY_DOMAIN."
done

echo "FreeSWITCH smoke test completed successfully."
