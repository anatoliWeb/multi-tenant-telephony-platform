# Frontend Boundaries

## Purpose

This document records which frontend owns which part of the product surface.

## Angular Responsibility

Angular is the tenant-facing application.

Angular owns:

- tenant dashboard;
- chat;
- contacts;
- softphone;
- calls;
- conferences;
- extensions;
- phone numbers;
- queues;
- IVR;
- reports;
- tenant billing views;
- user settings.

Angular should continue to treat the backend as the authorization authority.

## Vue Responsibility

Vue is the platform administration application.

Vue owns:

- tenant administration from the platform perspective;
- platform users;
- global permission catalog;
- protected system roles;
- monitoring;
- queue/Horizon/Reverb state;
- FreeSWITCH health in the future;
- support tools;
- platform statistics;
- global billing administration.

## Shared Frontend Rules

- Angular and Vue do not duplicate features without a documented reason.
- Both frontends use the same Laravel API.
- Laravel remains the authorization authority.
- Frontend guards improve UX but do not replace backend authorization.
- SIP.js must be isolated in a reusable Angular call-control layer.
- Chat components must not contain SIP.js session logic.
- Tenant-specific UX should stay in Angular.
- Platform-only operational UX should stay in Vue.

## Current State Notes

- Angular already owns the tenant chat and realtime experience.
- Angular now also owns the tenant IVR foundation UI under `/ivr`.
- Angular now has the first tenant softphone foundation slice with a
  permission-aware launcher and tenant-scoped SIP profile modal.
- Stage 15.2 adds a local-demo credential gate for Angular registration only
  when the backend explicitly allows it in local development.
- Stage 15.3 lets Angular attempt real local-demo SIP registration and local
  extension calls, but browser WSS/TLS trust still governs whether the browser
  can actually complete the handshake on a given machine.
- Angular SIP profiles must keep browser-facing SIP domains and WSS URLs
  separate from Docker runtime lookup domains used by FreeSWITCH provisioning.
- The browser must never be given a Docker runtime IP just because the
  container uses one internally for directory lookup.
- Vue already owns the platform dashboard, admin monitoring surface, and the tenant-support IVR visibility page.
- Vue also has a planned SIP.js/WebRTC softphone slice for platform admin and support workflows, but it is not implemented yet.
- Telephony UI work has started for routing foundation slices, and the reusable
  Angular call-control layer now exists as a foundation shell, but live SIP
  registration remains intentionally disabled until tenant-safe PBX provisioning exists.
