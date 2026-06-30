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

## Layout

- `conf/` - reserved for future FreeSWITCH configuration files;
- `log/` - reserved for future FreeSWITCH log mounts;
- `recordings/` - reserved for future local call recordings;
- `tls/` - reserved for future local TLS certificates for WSS or SIP TLS experiments.

These folders are scaffolding only in this foundation slice. The image's built-in
defaults are used for now.

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
the Stage 14 Docker profile foundation.
