# ADR 0011: Secure Transport Is Required Before Future E2EE

- Status: Accepted
- Date: 2026-06-23

## Context

Future end-to-end encryption work requires a secure transport baseline first.

## Decision

HTTPS, WSS, TLS, and secure credential handling are required before future E2EE features are planned or implemented.

## Consequences

- Transport security must be treated as a prerequisite.
- Media and signaling security cannot rely on E2EE as a substitute for baseline transport hardening.
- Incompatible legacy transport assumptions must not be carried forward.
