# ADR 0009: FreeSWITCH Provides SIP, RTP, Media, and Conference Mixing

- Status: Accepted
- Date: 2026-06-23

## Context

The future telephony stack needs a concrete PBX/media provider target.

## Decision

FreeSWITCH is the planned external provider for SIP, RTP, media handling, and conference mixing.

## Consequences

- Laravel must integrate through provider contracts instead of direct FreeSWITCH classes.
- Media and conference logic stays outside generic telephony services.
- The platform can defer the actual FreeSWITCH integration until tenant isolation is ready.
