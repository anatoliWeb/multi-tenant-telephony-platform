#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../../.." && pwd)"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"
PASSWORD="${FREESWITCH_DEFAULT_SIP_PASSWORD:-change_me_local_demo_only}"
USERS="${FREESWITCH_DEMO_USERS:-1001 1002 2001 2002}"
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

validate_runtime_domain() {
  runtime_domain="$1"

  if [ -z "$runtime_domain" ]; then
    echo "FreeSWITCH runtime domain is empty." >&2
    exit 1
  fi

  case "$runtime_domain" in
    *[!0-9A-Za-z.-]*)
      echo "FreeSWITCH runtime domain looks invalid: $runtime_domain" >&2
      exit 1
      ;;
  esac
}

copy_file_into_container() {
  source_file="$1"
  target_path="$2"
  docker cp "$source_file" "$CONTAINER_ID:$target_path" >/dev/null
}

trim_output() {
  tr -d '\r' | awk 'NF { value = $0 } END { print value }'
}

assert_full_user_xml() {
  user="$1"
  domain="$2"
  xml="$(run_fs_cli "find_user_xml id $user $domain")"

  if printf '%s\n' "$xml" | grep -q 'type="pointer"'; then
    echo "FreeSWITCH returned pointer XML for $user@$domain; full auth XML is required." >&2
    exit 1
  fi

  if ! printf '%s\n' "$xml" | grep -q '<param name="password" value="'; then
    echo "FreeSWITCH XML for $user@$domain is missing a password parameter." >&2
    exit 1
  fi

  if ! printf '%s\n' "$xml" | grep -q "<user id=\"$user\""; then
    echo "FreeSWITCH XML for $user@$domain is missing the expected user entry." >&2
    exit 1
  fi
}

assert_user_password() {
  user="$1"
  domain="$2"
  password="$3"
  direct_lookup="$(run_fs_cli "user_data $user@$domain attr password" | trim_output)"

  if [ "$direct_lookup" = "$password" ]; then
    echo "Verified password lookup for $user@$domain."
    return 0
  fi

  xml="$(run_fs_cli "find_user_xml id $user $domain")"

  if printf '%s\n' "$xml" | grep -q "<param name=\"password\" value=\"$password\""; then
    echo "Verified password param for $user@$domain via find_user_xml."
    return 0
  fi

  echo "FreeSWITCH password lookup failed for $user@$domain." >&2
  exit 1
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

# Browser SIP values must stay browser-reachable. FreeSWITCH directory lookup
# can use a different runtime domain inside Docker, so we resolve it separately
# for provisioning checks instead of assuming both values are identical.
BROWSER_DOMAIN_FILE="$SCRIPT_DIR/../conf/directory/localhost.xml"
LOCAL_DEMO_DIALPLAN_FILE="$SCRIPT_DIR/../conf/dialplan/local-demo-extensions.xml"
DIRECTORY_DOMAIN="$(resolve_directory_domain)"
RUNTIME_DOMAIN="$(run_fs_cli "global_getvar local_ip_v4" | trim_output)"

validate_runtime_domain "$RUNTIME_DOMAIN"

if [ ! -f "$BROWSER_DOMAIN_FILE" ]; then
  echo "Browser domain template is missing: $BROWSER_DOMAIN_FILE" >&2
  exit 1
fi

if [ ! -f "$LOCAL_DEMO_DIALPLAN_FILE" ]; then
  echo "Local demo dialplan template is missing: $LOCAL_DEMO_DIALPLAN_FILE" >&2
  exit 1
fi

RUNTIME_DOMAIN_FILE="$TMP_DIR/$RUNTIME_DOMAIN.xml"
sed "s/<domain name=\"localhost\">/<domain name=\"$RUNTIME_DOMAIN\">/" "$BROWSER_DOMAIN_FILE" > "$RUNTIME_DOMAIN_FILE"
RUNTIME_DIALPLAN_FILE="$TMP_DIR/00_local-demo-extensions.xml"
sed "s/__RUNTIME_DOMAIN__/$RUNTIME_DOMAIN/g" "$LOCAL_DEMO_DIALPLAN_FILE" > "$RUNTIME_DIALPLAN_FILE"
INLINE_DIALPLAN_FILE="$TMP_DIR/local-demo-route.xml"
sed '1d;$d' "$RUNTIME_DIALPLAN_FILE" > "$INLINE_DIALPLAN_FILE"

rewrite_inline_dialplan() {
  target_path="$1"
  anchor_line="$2"
  local_source="$3"
  local_target="$TMP_DIR/$(basename "$target_path")"
  local_rewrite="$TMP_DIR/rewrite-local-demo.awk"

  docker cp "$CONTAINER_ID:$target_path" "$local_target" >/dev/null

  cat > "$local_rewrite" <<'AWK'
BEGIN {
  while ((getline line < block_file) > 0) block = block line ORS
  close(block_file)
  begin_marker = "  <!-- BEGIN LOCAL DEMO EXTENSION BRIDGE -->"
  end_marker = "  <!-- END LOCAL DEMO EXTENSION BRIDGE -->"
  inserted = 0
  skipping = 0
}
$0 == begin_marker {
  skipping = 1
  next
}
$0 == end_marker {
  skipping = 0
  next
}
$0 ~ /^  <!-- Local demo bridge rules for FreeSWITCH development only\./ {
  skipping = 1
  next
}
{
  if (skipping) {
    if ($0 == anchor_line) {
      print begin_marker
      printf "%s", block
      print end_marker
      print
      inserted = 1
      skipping = 0
    }
    next
  }

  if ($0 == anchor_line) {
    print begin_marker
    printf "%s", block
    print end_marker
    print
    inserted = 1
    next
  }

  print
}
END {
  if (!inserted) {
    print begin_marker
    printf "%s", block
    print end_marker
  }
}
AWK

  awk -v anchor_line="$anchor_line" -v block_file="$local_source" -f "$local_rewrite" "$local_target" > "$local_target.new"
  mv "$local_target.new" "$local_target"
  docker cp "$local_target" "$CONTAINER_ID:$target_path" >/dev/null
}

for user in $USERS; do
  USER_FILE="$TMP_DIR/$user.xml"

  cat > "$USER_FILE" <<EOF
<include>
  <!-- Local demo user for FreeSWITCH development only. This static XML is a fallback,
       not the SaaS source of truth, and must never be reused in production.
       Laravel-backed provisioning remains the long-term target. -->
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

copy_file_into_container "$BROWSER_DOMAIN_FILE" "/usr/local/freeswitch/conf/directory/localhost.xml"
copy_file_into_container "$RUNTIME_DOMAIN_FILE" "/usr/local/freeswitch/conf/directory/$RUNTIME_DOMAIN.xml"
docker compose -f "$COMPOSE_FILE" exec -T freeswitch sh -lc "rm -f /usr/local/freeswitch/conf/dialplan/public/00_local-demo-extensions.xml /usr/local/freeswitch/conf/dialplan/default/00_local-demo-extensions.xml"
rewrite_inline_dialplan "/usr/local/freeswitch/conf/dialplan/public.xml" '    <extension name="public_extensions">' "$INLINE_DIALPLAN_FILE"
rewrite_inline_dialplan "/usr/local/freeswitch/conf/dialplan/default.xml" '    <extension name="Local_Extension">' "$INLINE_DIALPLAN_FILE"

run_fs_cli "reloadxml" >/dev/null

echo "Provisioned local demo users: $USERS"
echo "Browser SIP domain: $BROWSER_SIP_DOMAIN"
echo "Browser SIP WSS URL: $BROWSER_WSS_URL"
echo "FreeSWITCH directory lookup domain: $DIRECTORY_DOMAIN"
echo "FreeSWITCH runtime SIP domain: $RUNTIME_DOMAIN"
echo "After reload/restart, browser SIP registrations may be cleared. Re-register 1001 and 1002 before call testing."

for DIALPLAN_CONTEXT in public default; do
  if ! run_fs_cli "xml_locate dialplan context name $DIALPLAN_CONTEXT" | grep -q "local-demo-extension-bridge"; then
    echo "FreeSWITCH dialplan verification failed for $DIALPLAN_CONTEXT context." >&2
    exit 1
  fi
done

for domain in "$BROWSER_SIP_DOMAIN" "$RUNTIME_DOMAIN"; do
  for user in 1001 1002 2001 2002; do
    if [ "$(run_fs_cli "user_exists id $user $domain" | trim_output)" != "true" ]; then
      echo "FreeSWITCH did not resolve demo user $user in domain $domain." >&2
      exit 1
    fi

    echo "Verified demo user $user in domain $domain."

    assert_user_password "$user" "$domain" "$PASSWORD"
    assert_full_user_xml "$user" "$domain"
  done
done

REGISTRATIONS_OUTPUT="$(run_fs_cli "show registrations")"
printf '%s\n' "$REGISTRATIONS_OUTPUT"

if printf '%s\n' "$REGISTRATIONS_OUTPUT" | grep -q '0 total'; then
  echo "No active browser registrations yet; this is normal after container recreate until the browsers register again."
fi

echo "FreeSWITCH runtime demo provisioning complete."
echo "Browser softphones must be registered again after container recreate."
