#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../../.." && pwd)"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"
PASSWORD="${FREESWITCH_DEFAULT_SIP_PASSWORD:-change_me_local_demo_only}"
USERS="${FREESWITCH_DEMO_USERS:-1001 1002 2001 2002}"

run_fs_cli() {
  docker compose -f "$COMPOSE_FILE" exec -T freeswitch fs_cli -x "$1"
}

resolve_lookup_domain() {
  if [ -n "${FREESWITCH_SIP_DOMAIN:-}" ]; then
    printf '%s\n' "$FREESWITCH_SIP_DOMAIN"
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
  echo "FreeSWITCH must be running before provisioning demo users." >&2
  echo "Start it with: docker compose --profile freeswitch up -d freeswitch" >&2
  exit 1
fi

CONTAINER_ID="$(docker compose -f "$COMPOSE_FILE" ps -q freeswitch)"

if [ -z "$CONTAINER_ID" ]; then
  echo "FreeSWITCH container is not running." >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

LOOKUP_DOMAIN="$(resolve_lookup_domain)"

for user in $USERS; do
  USER_FILE="$TMP_DIR/$user.xml"

  cat > "$USER_FILE" <<EOF
<include>
  <!-- Local demo user for FreeSWITCH development only. This static XML is a fallback,
       not the SaaS source of truth, and must never be reused in production. -->
  <user id="$user">
    <params>
      <param name="password" value="$PASSWORD"/>
      <param name="vm-password" value="$user"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="$user"/>
      <variable name="user_context" value="default"/>
      <variable name="effective_caller_id_name" value="Extension $user"/>
      <variable name="effective_caller_id_number" value="$user"/>
      <variable name="outbound_caller_id_name" value="\$\${outbound_caller_name}"/>
      <variable name="outbound_caller_id_number" value="\$\${outbound_caller_id}"/>
      <variable name="callgroup" value="techsupport"/>
    </variables>
  </user>
</include>
EOF

  docker cp "$USER_FILE" "$CONTAINER_ID:/usr/local/freeswitch/conf/directory/default/$user.xml" >/dev/null
done

run_fs_cli "reloadxml" >/dev/null
run_fs_cli "sofia profile internal restart" >/dev/null

echo "Provisioned local demo users: $USERS"
echo "Using FreeSWITCH lookup domain: $LOOKUP_DOMAIN"

for user in 1001 1002; do
  if [ "$(run_fs_cli "user_exists id $user $LOOKUP_DOMAIN" | tr -d '\r' | awk 'NF { value = $0 } END { print value }')" != "true" ]; then
    echo "FreeSWITCH did not resolve demo user $user in domain $LOOKUP_DOMAIN." >&2
    exit 1
  fi

  echo "Verified demo user $user in domain $LOOKUP_DOMAIN."
done

echo "Demo directory provisioning completed successfully."
