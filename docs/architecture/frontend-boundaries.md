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
- Vue already owns the platform dashboard and admin monitoring surface.
- Telephony UI work has not started yet.
- The reusable call-control layer is a planned future Angular boundary.
