# ADR 0008: PBX Integrations Use Contracts and Adapters

- Status: Accepted
- Date: 2026-06-23

## Context

Telephony will eventually need to support provider implementations without coupling the domain to one vendor.

## Decision

PBX integrations will be accessed through contracts and adapters.

## Consequences

- Telephony domain services stay provider-neutral.
- Provider-specific behavior lives in `Integrations`.
- Testing can use fake adapters without leaking vendor details into the domain model.
