# FreeSWITCH Docker Profile

This directory holds the optional local FreeSWITCH profile scaffolding for Stage 14.

## What this profile is for

- local-only VoIP experiments on a developer machine;
- future SIP/WebRTC and call-control wiring;
- keeping FreeSWITCH runtime boundaries outside the Laravel telephony domain.

The current profile uses `servicebots/freeswitch:latest`, which boots reliably
in this environment and remains suitable for local development.

## What this profile is not for

- replacing the deterministic fake PBX provider;
- storing real SIP credentials in git;
- enabling SIP.js or browser softphone behavior yet;
- production deployment without a separate security review.

## Local demo credential gate

Stage 15.2 adds an explicit local-only gate for demo SIP credentials.

The backend only returns a password when all of these are true:

- `APP_ENV=local`
- `FREESWITCH_ENABLED=true`
- `FREESWITCH_LOCAL_DEMO_CREDENTIALS=true`

The matching demo password is `change_me_local_demo_only`. It is intentionally
unsafe for production and must never be reused outside local development.

The Angular softphone keeps that password in service memory only. It is not
written to browser storage, and the public profile response stays metadata-only
outside the local-demo gate.

Stage 15.3 adds a browser registration attempt and two-browser call path for
local development. The browser must still trust the local FreeSWITCH
certificate chain for `wss://localhost:7443`, otherwise SIP.js will fail the
transport handshake with a clear local WSS/TLS error.

## Layout

- `conf/` - reserved for future FreeSWITCH configuration files;
- `log/` - reserved for future FreeSWITCH log mounts;
- `recordings/` - reserved for future local call recordings;
- `tls/` - reserved for future local TLS certificates for WSS or SIP TLS experiments.

The current repo uses the image's built-in defaults. The FreeSWITCH config tree
inside the running container is located at `/usr/local/freeswitch/conf`, with
the demo user directory at `/usr/local/freeswitch/conf/directory/default`.

The files in `conf/directory/default/` are local scaffolding examples only.
They exist so the repository documents the exact shape of the demo users
without bind-mounting an incomplete `/etc/freeswitch` tree over the container.

The Event Socket port is bound to `127.0.0.1` so it stays local-only and does
not expose unsafe call-control access outside the developer machine.

The foundation slice does not bind-mount `/etc/freeswitch`. An incomplete local
config tree can shadow the image defaults and prevent FreeSWITCH from booting.

## Local startup

Use the profile only when you want to expose the FreeSWITCH container alongside the default stack:

```bash
docker compose --profile freeswitch up -d freeswitch
```

Check the running container:

```bash
docker ps --filter "name=freeswitch"
```

Check FreeSWITCH runtime health:

```bash
docker compose exec -T freeswitch fs_cli -x "status"
```

Expected:

```text
FreeSWITCH ... is ready
```

Check SIP profiles:

```bash
docker compose exec -T freeswitch fs_cli -x "sofia status"
```

Expected:

```text
internal RUNNING
external RUNNING
```

Stop the profile:

```bash
docker compose --profile freeswitch stop freeswitch
```

The Laravel application still defaults to the fake telephony provider until the
future call-control integration layer explicitly switches behavior.

## Demo provisioning

Provision local demo users after the container is up:

```bash
docker compose --profile freeswitch up -d freeswitch
./docker/freeswitch/scripts/provision-demo-users.sh
```

The script provisions `1001`, `1002`, `2001`, and `2002` by default so it can
cover both the documented demo pair and the current Angular demo seed data.
Override that list with `FREESWITCH_DEMO_USERS` if you need a narrower set.

The script reloads XML and restarts the internal SIP profile after copying the
demo files, then verifies that `1001` and `1002` resolve with the correct
lookup syntax:

```bash
docker compose exec -T freeswitch fs_cli -x "user_exists id 1001 <domain>"
docker compose exec -T freeswitch fs_cli -x "user_exists id 1002 <domain>"
```

The `<domain>` value comes from the running container's `local_ip_v4` when
`FREESWITCH_SIP_DOMAIN` is not set. In this environment the runtime lookup
domain has been observed as `172.18.0.12`, but that value is container-network
specific and may differ on another machine.

If you need to inspect the resolved XML directly, use:

```bash
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 1001 <domain>"
docker compose exec -T freeswitch fs_cli -x "find_user_xml id 1002 <domain>"
```

If you prefer to copy the example files manually, use the discovered container
path:

```bash
docker cp docker/freeswitch/conf/directory/default/1001.xml $(docker compose ps -q freeswitch):/usr/local/freeswitch/conf/directory/default/1001.xml
docker cp docker/freeswitch/conf/directory/default/1002.xml $(docker compose ps -q freeswitch):/usr/local/freeswitch/conf/directory/default/1002.xml
```

## Cleanup

If an earlier compose definition left stale FreeSWITCH containers behind, clean
only the project-scoped containers before retrying:

```bash
docker ps -a --filter "name=freeswitch"
docker rm <container_id_or_name>
```

Remove only containers that belong to this project. Do not prune unrelated
containers.

## Known limitation

`mod_xml_curl` may log `Binding has no url` because backend-driven dynamic
directory and dialplan integration is not configured yet. That is acceptable for
the Stage 14 and Stage 15.2 foundations.

The static XML demo users in `conf/directory/default/` remain fallback
scaffolding. They document the local demo shape, but they are not the SaaS
source of truth for SIP credentials.
