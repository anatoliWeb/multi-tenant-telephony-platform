# System Context

## Purpose

This document describes the current and near-future context around the platform so the backend and frontend boundaries stay explicit.

## Actors

- Tenant users who work in the Angular application.
- Platform administrators who work in the Vue administration application.
- Integration callers that use API tokens or webhook credentials.
- Queue workers and Horizon processing asynchronous jobs.
- The scheduler process that runs periodic application tasks.

## Runtime Context

- Angular talks to the Laravel API for tenant-facing work.
- Vue talks to the same Laravel API for platform administration work.
- Laravel is the only backend business-logic runtime in the current baseline.
- MySQL stores the shared application data.
- Redis backs cache, queues, Horizon, and realtime-related workloads.
- Reverb delivers application realtime messages.
- Nginx fronts the stack in Docker.

## Trust Boundaries

- Frontend code is not a source of authorization truth.
- Laravel owns authorization, ownership checks, and cross-tenant safety rules.
- Shared data access must be fail-closed when tenant context is missing or invalid.
- Realtime payloads are outputs of application behavior, not business-state sources.
- Future FreeSWITCH and PBX integrations must stay behind Laravel provider contracts.

## Current Boundary Notes

- Angular currently owns the tenant UX surface.
- Vue currently owns platform administration and operational visibility.
- The current baseline is still a modular monolith.
- Multi-tenancy has not been implemented yet.
- Telephony has not been implemented yet.

## Context Summary

The platform is intentionally a single Laravel system with two frontends and shared infrastructure:

- Angular for tenant work.
- Vue for platform administration.
- Laravel for backend orchestration and security.
- Redis and Reverb for async and realtime support.
- MySQL for shared persistence.

This structure is the target foundation for the multi-tenancy phase.
