# Extension Rules

## Purpose

These are the mandatory rules for new features in the planned architecture.

## Backend Feature Checklist

Before adding a backend feature:

- identify the owning module;
- define permissions;
- define tenant ownership;
- define the API contract;
- use Form Request validation;
- use API Resources or the existing response contract;
- define events;
- define queue requirements;
- define audit requirements;
- define monitoring requirements;
- define seed/demo data;
- add tests;
- update OpenAPI;
- update documentation.

## Angular Feature Checklist

- feature module or standalone feature boundary;
- route guard;
- permission-aware navigation;
- API service;
- state ownership;
- loading state;
- empty state;
- error state;
- tests;
- translations;
- realtime subscriptions when required.

## Vue Feature Checklist

- platform-only responsibility;
- route metadata;
- permission guard;
- store/service boundary;
- monitoring or administration purpose;
- tests;
- translations.

## Integration Feature Checklist

- provider contract;
- provider registry;
- provider DTOs;
- idempotency;
- retries;
- timeout;
- failure state;
- manual replay;
- audit trail;
- correlation ID;
- sensitive data redaction.
