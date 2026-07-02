# FreeSWITCH Docker Profile

This directory holds the optional local FreeSWITCH profile scaffolding for Stage 14.

## What this profile is for

- local-only VoIP experiments on a developer machine;
- future SIP/WebRTC and call-control wiring;
- keeping FreeSWITCH runtime boundaries outside the Laravel telephony domain.

The current profile uses `servicebots/freeswitch:latest`, which boots cleanly
in this workspace and remains the verified image for this project. The Compose
service now uses the stable container name
`multi-tenant-telephony-platform-freeswitch` so local docs and scripts do not
depend on Compose's generated suffix. Because `container_name` fixes the
service to a single instance, it is intentionally not meant to be scaled with
Compose.

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

The Angular call-control layer now also keeps the remote audio element
unmuted and renders basic media diagnostics in the softphone modal, which
helps separate autoplay and playback problems from SIP registration failures.
That UI change does not add any SIP.js provider logic or FreeSWITCH coupling.

The local demo dialplan now bridges the live Sofia contact returned by
`sofia_contact(*/<extension>@<runtime-domain>)` instead of bridging
`user/<extension>@<runtime-domain>` directly. That preserves the WebSocket
contact string, including the generated `fs_path`, and avoids the
`USER_NOT_REGISTERED` / `INCOMPATIBLE_DESTINATION` failure path when a SIP.js
browser session is already registered.

Stage 15.3 adds a browser registration attempt and two-browser call path for
local development. The browser must still trust the local FreeSWITCH
certificate chain for `wss://localhost:7443`, otherwise SIP.js will fail the
transport handshake with a clear local WSS/TLS error.
When the browser does not trust that local certificate chain, the Angular demo
can fall back to `ws://localhost:5066` in local development only. That fallback
exists to avoid browser TLS trust problems with self-signed local WSS
certificates; production should keep using trusted WSS.

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
They remain the local-demo fallback only; the Laravel-backed directory
endpoint scaffold is the target source of truth for DB-driven provisioning.

The provisioning script also copies a small `localhost` domain alias into the
running container so browser-facing SIP auth can resolve `1001@localhost`,
`1002@localhost`, `2001@localhost`, and `2002@localhost` without changing the
browser SIP domain or exposing Docker runtime IPs to Angular.

The alias must be full auth XML with a `password` parameter for each demo
user. A pointer-only directory entry is not enough for browser SIP
registration. The provisioning script also generates a temporary copy of that
same XML for the runtime profile domain detected from
`global_getvar local_ip_v4`, then copies both files into the running container
without committing the runtime-domain file to git. The dialplan side now uses
a single inline extension strategy: the script rewrites the live `public.xml`
and `default.xml` files with one marked local-demo block before the stock
routes. On this image, `find_user_xml id <user> <domain>` is the most
reliable way to verify the resolved auth XML.

The Event Socket port is bound to `127.0.0.1` so it stays local-only and does
not expose unsafe call-control access outside the developer machine.

The foundation slice does not bind-mount `/etc/freeswitch`. An incomplete local
config tree can shadow the image defaults and prevent FreeSWITCH from booting.

If you run `docker compose --profile freeswitch down` or recreate the container,
the runtime-copied XML files and SIP registrations are cleared with it. Re-run
the provisioning script before testing browser registration again.

## Local startup

Use the profile only when you want to expose the FreeSWITCH container alongside the default stack:

```bash
docker compose --profile freeswitch up -d freeswitch
```

If you previously started the profile before the stable container name landed,
remove the old generated container once and start again:

```bash
docker compose --profile freeswitch stop freeswitch
docker rm multi-tenant-telephony-platform-freeswitch-1 2>/dev/null || true
docker compose --profile freeswitch up -d freeswitch
```

PowerShell equivalent:

```powershell
docker compose --profile freeswitch stop freeswitch
docker rm multi-tenant-telephony-platform-freeswitch-1
docker compose --profile freeswitch up -d freeswitch
```

If Bash provisioning cannot run after recreate, use the manual Docker copy and
reload sequence from the demo provisioning section below.

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
docker compose exec -T freeswitch fs_cli -x "sofia status profile internal"
```

Expected:

```text
internal RUNNING
external RUNNING
WS-BIND-URL
WSS-BIND-URL
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

PowerShell fallback when the shell script cannot be launched directly:

If PowerShell is the only shell available, run the provisioning script logic
from `docker/freeswitch/scripts/provision-demo-users.sh` and keep the same
inline-extension-body strategy. Do not copy the old `00_` include-file recipe
into the live dialplan; that was the source of the malformed XML.

The provisioning script refreshes the runtime `public.xml` and `default.xml`
dialplans so the actual local demo route body appears before the stock
`public_extensions` and `Local_Extension` rules, keeping `1001` and `1002` on
the live Sofia contact bridge instead of falling back to `bridge(user/...)`.

When the route is active, the live logs should contain markers such as:

```text
INFO local-demo bridge 1001 -> 1002
INFO local-demo contact 1002: sofia/internal/...
```

The script uses `reloadxml` only for dialplan changes. It does not restart the
Sofia profile, because a restart clears browser registrations.

The script provisions `1001`, `1002`, `2001`, and `2002` by default so it can
cover both the documented demo pair and the current Angular demo seed data.
Override that list with `FREESWITCH_DEMO_USERS` if you need a narrower set.

The script reloads XML after copying the demo files, then verifies that
`1001` and `1002` resolve with the correct lookup syntax:

```bash
docker compose exec -T freeswitch fs_cli -x "user_exists id 1001 <domain>"
docker compose exec -T freeswitch fs_cli -x "user_exists id 1002 <domain>"
```

Browser-facing SIP values stay separate from the FreeSWITCH runtime directory
lookup domain:

- browser SIP domain: `localhost` by default in local development;
- browser SIP WS URL: `ws://localhost:5066` when the local demo fallback is
  enabled;
- browser SIP WSS URL: `wss://localhost:7443` by default in local development;
- FreeSWITCH directory lookup domain: `FREESWITCH_DIRECTORY_DOMAIN` when set,
  otherwise the running container's `global_getvar local_ip_v4`.

The Laravel directory endpoint scaffold is local-only and tenant-safe. It uses
a configured tenant id as an explicit demo constraint rather than guessing
tenant identity from a raw FreeSWITCH request. That keeps the DB-backed
directory scaffold in place without pretending production `mod_xml_curl`
wiring is ready yet.

In this environment the runtime lookup domain has been observed as
`172.18.0.12`, but that value is container-network specific and may differ on
another machine. The provisioning script resolves it dynamically and writes a
temporary runtime-domain XML copy for the running container. Do not hardcode it
into browser-facing SIP URIs or commit it to the repository.

Docker-side provisioning for `1001`, `1002`, `2001`, and `2002` has been
verified with both `user_exists` and `find_user_xml` on the browser domain and
the runtime domain, and the demo dialplan now bridges the local visible pair
through the runtime realm. Live browser registration is still a manual
follow-up when the in-app browser-control bridge is unavailable.

Host port mappings for the optional profile:

- `5066/tcp` for local browser SIP WS fallback;
- `7443/tcp` for browser SIP WSS;
- `127.0.0.1:8021/tcp` for the local-only Event Socket.

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

If a local demo call still returns `488 Not Acceptable Here` or
`INCOMPATIBLE_DESTINATION`, check whether the browser registered contact is
being bridged directly. The safe local path is to resolve the contact with
`sofia_contact` and then bridge that exact value. If the resolved contact is
empty, return `480 Temporarily Unavailable` and map it as a missing
registration, not as a media failure.

In this workspace `safarov/freeswitch:1.10.12` was tried during a regression
check, but it left the container unhealthy and `fs_cli` unreachable. Do not
switch this project to that image unless it is revalidated end-to-end on the
target machine.

The static XML demo users in `conf/directory/default/` remain fallback
scaffolding. They document the local demo shape, but they are not the SaaS
source of truth for SIP credentials.

The long-term target is Laravel-backed directory provisioning through a proper
PBX integration layer. Static XML files are only the local bootstrap fallback
until that architecture is implemented.
