# Contacts

## Scope

The Contacts module is now implemented as a tenant-owned address book foundation.

Implemented in this slice:

- tenant-owned contacts;
- multiple phone numbers per contact;
- optional email addresses per contact;
- tenant-owned tags;
- tenant-scoped search and pagination;
- tenant-scoped phone lookup;
- duplicate prevention inside a tenant;
- CSV import validation and import foundation;
- tenant-scoped CSV export;
- Angular tenant UI;
- deterministic demo and test seed data;
- backend and frontend test coverage.

Deferred in this slice:

- favorites;
- tenant user directory merge;
- extension directory integration;
- recent-call derived contacts;
- private-contact isolation;
- advanced CRM taxonomy;
- real calls;
- SIP.js;
- FreeSWITCH integration.

## Ownership Model

Every contact belongs to exactly one tenant.

Primary entities:

- `Contact`
- `ContactPhone`
- `ContactEmail`
- `ContactTag`

Rules:

- `tenant_id` is required on all contact-owned rows;
- tenant ownership is derived from `TenantContext`;
- client-supplied `tenant_id` is ignored during contact writes;
- ordinary CRUD does not allow moving a contact between tenants;
- route model binding fails closed outside the active tenant.

## Phone Normalization

Service: `App\Services\Contacts\PhoneNumberNormalizer`

Behavior:

- trims whitespace and common separators;
- preserves a leading `+` when present;
- extracts an optional extension without corrupting the canonical number;
- normalizes searchable values deterministically;
- rejects clearly invalid inputs;
- does not assume a default country unless `CONTACTS_DEFAULT_COUNTRY` is configured.

Current duplicate policy:

- the same normalized phone may exist in different tenants;
- the same normalized phone is unique inside one tenant;
- the same normalized email is unique inside one tenant;
- if no phone or email is present, the service falls back to a name plus company duplicate signal.

## API

Routes:

- `GET /api/v1/contacts`
- `POST /api/v1/contacts`
- `GET /api/v1/contacts/search`
- `GET /api/v1/contacts/lookup-phone`
- `GET /api/v1/contacts/export`
- `POST /api/v1/contacts/import/validate`
- `POST /api/v1/contacts/import`
- `GET /api/v1/contacts/{contact}`
- `PUT /api/v1/contacts/{contact}`
- `PATCH /api/v1/contacts/{contact}`
- `DELETE /api/v1/contacts/{contact}`
- `GET /api/v1/contact-tags`
- `POST /api/v1/contact-tags`
- `PUT /api/v1/contact-tags/{tag}`
- `DELETE /api/v1/contact-tags/{tag}`

Tenant permissions:

- `tenant.contacts.view`
- `tenant.contacts.create`
- `tenant.contacts.update`
- `tenant.contacts.delete`
- `tenant.contacts.import`
- `tenant.contacts.export`
- `tenant.contacts.manage_tags`

## Import and Export Safety

Import:

- derives tenant ownership from `TenantContext`;
- ignores any tenant column in uploaded CSV rows;
- validates file type and row shape;
- reports row-level validation errors;
- rejects cross-tenant tag references.

Export:

- includes only rows from the active tenant;
- requires export permission;
- escapes CSV cells to prevent formula injection.

## Angular Behavior

The Angular tenant app now includes a contacts feature module with:

- list and details views;
- search, filters, and pagination;
- create and edit modal workflow;
- multiple phones and emails;
- tag management during contact upsert;
- permission-aware actions;
- tenant-switch state reset.

## Explicit Non-Goals

Contacts are implemented.

Real calls are not implemented.

FreeSWITCH is not installed.

SIP.js is not integrated.

## Call Log Enrichment

Contacts now enrich call history in a tenant-safe way.

- normalized external numbers may resolve to a tenant contact during call-log creation;
- call logs still keep immutable number snapshots after contact lookup;
- renaming or deleting a contact does not rewrite historical call rows;
- cross-tenant contact matches fail closed.
