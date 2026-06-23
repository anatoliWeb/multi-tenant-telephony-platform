# ADR 0010: Browser Calling Uses a Reusable Call-Control Layer

- Status: Accepted
- Date: 2026-06-23

## Context

Browser calling will need to be reused across chat, contacts, users, extensions, calls, and conference-related workflows.

## Decision

A reusable call-control layer will be created for browser calling rather than embedding SIP.js logic in chat components.

## Consequences

- Call-control state stays separate from chat UI state.
- Browser calling can be reused across multiple Angular surfaces.
- Chat components stay focused on chat behavior.
