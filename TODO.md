# multi-tenant-telephony-platform

## TODO.md

## Status Legend

- `[x]` Completed and verified
- `[ ]` Not completed
- `PARTIAL` Partially verified or only partially implemented
- `BLOCKED` Cannot be completed until a dependency or environment issue is resolved

---

## Current Access Baseline

- [x] Demo and test data creation live in Laravel seeders and seeder support classes.
- [x] `TenantBootstrapService` is read-only.
- [x] Platform Admin tenant access is centralized through tenant permission resolution.
- [x] Tenant route middleware uses canonical unprefixed tenant permissions against the active tenant scope.
- [x] Local Docker database resets load schema dumps with `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` by default so the MySQL CLI does not reject the self-signed container certificate chain.
- PARTIAL Live browser verification still depends on a working local browser-control path, and backend testing reruns must stay sequential against `saas_testing`.

# 1. Project Strategy

The existing application is the foundation of `multi-tenant-telephony-platform`.

We do not create a new Laravel application.

We do not copy selected components into a second repository.

We do not delete or replace the existing Laravel backend, Angular frontend, Vue administration application, chat module, authentication, RBAC, realtime, notifications, activity logs, API response contract, queues, monitoring foundation, tests, seeders, or documentation structure.

The current application is extended incrementally.

## 1.1 Existing Foundation to Preserve

- [x] Laravel backend API
- [x] Angular tenant frontend
- [x] Vue platform administration frontend
- [x] Authentication
- [x] Custom RBAC
- [x] Roles and permissions
- [x] API response contract
- [x] OpenAPI foundation
- [x] Chat backend
- [x] Angular chat interface
- [x] Vue chat monitoring
- [x] Reverb and broadcasting
- [x] Notifications
- [x] Activity logs
- [x] Queue foundation
- [x] Horizon foundation
- [x] Docker architecture
- [x] Existing seeders and factories
- [x] Existing automated tests
- [x] Existing documentation structure

## 1.2 Development Rules

Before changing an existing component:

1. Inspect the current implementation.
2. Inspect the current tests.
3. Extend the current implementation when its architecture is suitable.
4. Refactor only when a verified architectural or security issue requires it.
5. Never create a duplicate implementation beside a working implementation.
6. Keep all source code, comments, documentation, UI labels, and commit messages in English.
7. Preserve backward compatibility unless a documented migration plan exists.

---

# 2. Approved Product Architecture

## 2.1 Laravel Backend

Laravel is the single backend API and business-logic application.

Responsibilities:

- authentication;
- authorization;
- tenant isolation;
- chat;
- telephony management;
- call control;
- conference management;
- notifications;
- events;
- queues;
- webhooks;
- billing;
- reporting;
- monitoring;
- PBX integrations.

## 2.2 Angular Tenant Frontend

Angular is the primary tenant-facing application.

Responsibilities:

- tenant dashboard;
- chat;
- contacts;
- browser softphone;
- call history;
- conference rooms;
- extensions;
- phone numbers;
- queues;
- IVR;
- reports;
- billing views;
- user settings.

## 2.3 Vue Platform Administration

Vue is the platform administration application.

Responsibilities:

- tenant administration;
- platform users;
- global permission catalog;
- system roles;
- monitoring;
- queue and Horizon status;
- Reverb status;
- FreeSWITCH status;
- activity logs;
- support tools;
- platform-wide statistics.

## 2.4 API Areas

Extend the existing API instead of introducing a second API implementation.

Target API areas:

```text
/api/v1/auth/*
/api/v1/user/*
/api/v1/tenant/*
/api/v1/platform/*
/api/v1/chat/*
/api/v1/telephony/*
/api/v1/integrations/*
/api/v1/webhooks/*
```

Existing route conventions and response contracts must be preserved unless a documented architectural issue requires a backward-compatible improvement.

---

# 3. Approved RBAC Rules

- [x] A role is a collection of permissions
- [x] A user may have multiple roles
- [x] Effective permissions are collected from assigned roles
- [x] The backend is the final authorization authority
- [x] The frontend hides unavailable pages and controls
- [x] Direct user permissions are excluded from the target first-release model
- [x] Permission-denial exceptions are excluded from the target first-release model
- [x] Tenants may create custom roles
- [x] Tenants may only use permissions from the system permission catalog
- [x] Tenants cannot create arbitrary permission names
- [x] Platform permissions and tenant permissions must be separated
- [x] Tenant ownership must be checked in addition to permissions

## 3.1 Required Permission Behavior

When a user does not have a required permission:

- the navigation item is hidden;
- the page cannot be opened;
- action buttons are hidden;
- Angular or Vue route access is rejected;
- Laravel returns `403`;
- the queued action is not dispatched;
- the PBX command is not sent;
- restricted realtime events are not delivered;
- restricted files and exports cannot be downloaded.

The existing RBAC implementation must be adapted, not duplicated.

---

# 4. Stage 0: Project Rename

## 4.1 Rename Implementation

- [x] Audit existing project and product names
- [x] Rename the project to `multi-tenant-telephony-platform`
- [x] Update Laravel application branding
- [x] Update Angular branding
- [x] Update Vue administration branding
- [x] Update HTML titles
- [x] Update navigation branding
- [x] Update package metadata where safe
- [x] Update Docker Compose naming
- [x] Update explicitly configured container names
- [x] Update documentation titles
- [x] Update environment examples
- [x] Update OpenAPI title and description
- [x] Update branding-related test expectations
- [x] Update obsolete demo branding
- [x] Update scripts and documentation that referenced obsolete container names
- [x] Preserve framework and technical names
- [x] Preserve API paths
- [x] Preserve database schema and migration history
- [x] Preserve existing business functionality
- [x] Complete the rename without creating a Git commit

## 4.2 Rename Validation

- [x] Search source-controlled files for obsolete names
- [x] Validate Docker Compose configuration
- [x] Validate JSON configuration files
- [x] Validate Laravel application naming
- [x] Validate Angular branding
- [x] Validate Vue branding
- [x] Confirm that no business logic was changed
- [x] Produce a rename report
- [x] Preserve intentional `saas` and `saas_testing` database identifiers
- [x] Exclude generated/runtime artifacts from manual rename work

## 4.3 Rename Result

- [x] Repository root already uses the correct name
- [x] No filesystem paths required renaming
- [x] Existing Laravel, Angular, Vue, Chat, RBAC, Realtime, Queue, Seeder, and Test foundations remain intact

---

# 5. Stage 1: Docker Startup and Baseline Validation

## 5.1 Existing Docker Services

- [x] Backend container is running and healthy
- [x] Nginx container is running
- [x] MySQL container is running and healthy
- [x] Redis container is running and healthy
- [x] Angular frontend container is running and healthy
- [x] Vue frontend container is running
- [x] Queue worker is running
- [x] Reverb is running
- [x] Dedicated Horizon profile is running
- [x] Dedicated scheduler process is running

## 5.2 Docker and Runtime Validation

- [x] Run `docker compose config`
- [x] Start the current Docker stack
- [x] Run `docker compose ps`
- [x] Verify core container health
- [x] Verify Laravel runtime
- [x] Verify database connectivity
- [x] Verify Redis connectivity
- [x] Verify queue worker startup
- [x] Verify Reverb startup
- [x] Verify Angular build
- [x] Verify Angular unit tests
- [x] Verify Vue build
- [x] Verify Vue unit tests
- [x] Run existing migrations
- [x] Run existing seeders
- [x] Create and grant access to `saas_testing`
- [x] Load baseline chat demo data
- [x] Produce `docs/baseline-validation.md`

## 5.3 Laravel Baseline Validation

- [x] `php artisan about`
- [x] `php artisan config:show app`
- [x] Laravel application name is correct
- [x] Cache driver is Redis
- [x] Queue driver is Redis
- [x] Broadcasting driver is Reverb
- [x] `public/storage` link exists
- [x] `php artisan route:list` loads successfully
- [x] Auth routes are present
- [x] RBAC routes are present
- [x] Chat routes are present
- [x] Horizon routes are present
- [x] API documentation routes are present
- [x] Migrations complete successfully
- [x] Seeders complete successfully

## 5.4 Authentication, RBAC, Chat, and Realtime Validation

- [x] Authentication routes are present
- [x] Sanctum session authentication foundation is present
- [x] Token authentication foundation is present
- [x] Roles and permissions routes are present
- [x] RBAC metadata route is present
- [x] Reverb starts on the configured port
- [x] Chat demo data contains more than 320 messages
- [x] Angular chat code builds successfully
- [x] Vue chat monitoring code builds successfully
- [x] Verify a real browser login flow
- [x] Angular application opens after authentication
- [x] Vue administration application opens after authentication
- [x] Verify logout through the browser
- [x] Verify direct chat between two browser sessions
- [x] Verify group chat through the browser
- [x] Verify realtime message delivery between two browser sessions
- [x] Verify typing and presence in live browser sessions
- [x] Verify permission-denied UI and API behavior manually

## 5.5 Test Validation

### Angular

- [x] Angular build passes
- [x] Angular tests pass
- [x] `16` test files pass
- [x] `113` Angular tests pass
- [ ] Remove or resolve the `NG8107` optional-chaining warning
- [ ] Review the `pusher-js` CommonJS optimization warning

### Vue

- [x] Vue build passes
- [x] Vue tests pass
- [x] `18` test files pass
- [x] `87` Vue tests pass
- [ ] Review the Dart Sass legacy JS API warning

### Backend

- [x] Focused baseline test slice passes
- [x] `ReadmeDocumentationTest` passes
- [x] `ReadmeUaDocumentationTest` passes
- [x] `OpenApiToolingTest` passes
- [x] `DockerImageOptimizationTest` passes
- [x] `NamingConsistencyTest` passes
- [x] `ArchitectureDocumentationTest` passes
- [x] Focused result: `6` passed, `5` skipped, `74` assertions
- [x] Run the complete backend test suite
- [x] Record complete backend pass/fail/skip totals
- [x] Resolve any failures from the complete backend suite

## 5.6 Baseline Definition of Done

- [x] Docker Compose configuration is valid
- [x] Required core containers are running
- [x] Laravel API runtime is operational
- [x] Angular builds and tests successfully
- [x] Vue builds and tests successfully
- [x] MySQL is operational
- [x] Redis is operational
- [x] Queue worker is running
- [x] Reverb is running
- [x] Existing migrations complete
- [x] Existing seeders complete
- [x] Baseline validation is documented
- [x] Complete backend test suite passes or failures are fully documented
- [x] Browser authentication flow is verified
- [x] Browser chat and realtime flow are verified
- [ ] Horizon runtime is verified
- [ ] Scheduler runtime is implemented and verified

---

# 6. Stage 2: Git Initialization and Baseline Commit

## 6.1 Git Safety

- [x] Root `.env` is ignored
- [x] `backend/.env` is ignored
- [x] `vendor` is ignored
- [x] `node_modules` is ignored
- [x] Laravel logs and runtime storage are ignored
- [x] Angular build output is ignored
- [x] Vue/Vite build output is ignored
- [x] Docker data is ignored
- [x] Run `git check-ignore -v .env`
- [x] Run `git check-ignore -v backend/.env`
- [x] Review all files before staging
- [x] Confirm no secrets, keys, certificates, logs, DB data, or recordings are staged

## 6.2 Git Initialization

- [x] Run `git init`
- [x] Rename the default branch to `main`
- [x] Run `git status`
- [x] Stage the verified baseline
- [x] Review staged files
- [x] Create the initial baseline commit

Recommended commit:

```text
chore: initialize multi-tenant telephony platform
```

---

# 7. Stage 3: Architecture Documentation

Do not reorganize the entire Laravel application before implementing the new domain.

Document the existing architecture first.

- [x] Update the architecture overview
- [x] Document the current request flow
- [x] Document reusable backend services
- [x] Document Angular and Vue responsibilities
- [x] Document API boundaries
- [x] Document existing RBAC behavior
- [x] Document current realtime channels
- [x] Document chat architecture
- [x] Document queue architecture
- [x] Document extension rules for new modules
- [x] Add architecture decision records

## 7.1 Required ADRs

- [x] Existing application is extended in place
- [x] Laravel remains the single backend
- [x] Angular is the tenant application
- [x] Vue is the platform administration application
- [x] Shared-database multi-tenancy
- [x] Roles are permission sets
- [x] Direct user permissions are excluded from the target first release
- [x] PBX integrations use contracts and adapters
- [x] FreeSWITCH provides SIP, RTP, media, and conference mixing
- [x] Browser calling uses a reusable call-control layer
- [x] Secure transport is required before future E2EE

---

# 8. Stage 4: Multi-Tenancy Foundation

Multi-tenancy is the first major new backend capability.

No telephony-domain implementation may begin before tenant isolation is operational and tested.

## 8.1 Tenant Domain

- [x] Create `Tenant`
- [x] Add tenant UUID
- [x] Add name
- [x] Add slug
- [x] Add status
- [x] Add timezone
- [x] Add locale
- [x] Add currency
- [x] Add settings
- [x] Add activation
- [x] Add suspension

## 8.2 Tenant Membership

- [x] Create `TenantMembership`
- [x] Connect existing users to tenants
- [x] Allow users to belong to multiple tenants
- [x] Add membership statuses
- [ ] Add invitation flow
- [ ] Add invitation acceptance
- [ ] Add membership activation
- [ ] Add membership suspension
- [ ] Add membership removal
- [ ] Prevent removal of the final tenant owner
- [x] Add tenant switching

## 8.3 Tenant Context

- [x] Create `TenantContext`
- [x] Add tenant resolution middleware
- [x] Resolve tenant from authenticated membership
- [ ] Resolve tenant from API tokens where required
- [ ] Resolve tenant from integration connections where required
- [x] Clear context safely after requests
- [ ] Propagate tenant context to queued jobs
- [ ] Propagate tenant context to listeners
- [ ] Propagate tenant context to scheduled commands
- [ ] Propagate tenant context to broadcasting

## 8.4 Tenant-Owned Existing Data

Adapt existing models instead of creating duplicate replacements.

- [x] Add tenant ownership to chat conversations
- [x] Add tenant ownership to messages where necessary
- [ ] Add tenant ownership to notifications
- [ ] Add tenant ownership to activity logs
- [ ] Add tenant ownership to tenant roles
- [ ] Add tenant ownership to relevant settings
- [x] Add tenant-aware route model binding
- [x] Add tenant-aware cache keys
- [x] Add tenant-aware storage paths
- [x] Add tenant-aware broadcasting channels

## 8.5 Tenant Isolation Tests

- [x] Cross-tenant reads are blocked
- [x] Cross-tenant updates are blocked
- [ ] Cross-tenant deletes are blocked
- [ ] Cross-tenant exports are blocked
- [x] Cross-tenant file access is blocked
- [x] Cross-tenant chat access is blocked
- [x] Cross-tenant realtime access is blocked
- [ ] Queue jobs cannot leak tenant context
- [x] Platform access requires platform permissions

---

# 9. Stage 5: Adapt Existing RBAC

Do not replace the existing custom RBAC implementation.

## 9.1 Backend Adaptations

- [x] Separate platform roles from tenant roles
- [x] Separate platform permissions from tenant permissions
- [x] Add tenant ownership to custom tenant roles
- [x] Preserve multiple roles per user
- [x] Keep the existing permission cache foundation
- [x] Prevent tenant administrators from assigning platform permissions
- [x] Prevent privilege escalation
- [x] Prevent deletion of protected system roles
- [x] Remove direct user permission behavior from the target UI
- [x] Remove denied-permission behavior from the target UI
- [x] Preserve legacy database structures only when removal would be unsafe
- [x] Do not expose legacy direct permission features through new APIs
- [x] Add migration or deprecation documentation if legacy structures remain
- [x] Add tenant-aware policy checks
- [x] Add tenant-aware permission cache keys
- [ ] Add role and permission audit logs

## 9.2 Angular Permission Integration

- [x] Tenant-aware route guards
- [x] Permission-aware navigation
- [x] Permission-aware buttons
- [x] Permission-aware tabs
- [x] Permission-aware widgets
- [ ] Permission-aware chat controls
- [ ] Permission-aware softphone controls
- [ ] Permission-aware conference controls

## 9.3 Vue Permission Integration

- [x] Platform route guards
- [x] Tenant administration permissions
- [x] Global permission catalog UI
- [x] System role protection
- [x] Platform monitoring permissions
- [x] Support-tool permissions

---

# 10. Stage 6: Adapt Existing Seeders and Demo Data

Do not discard the existing seeders.

## 10.1 Seeder Structure

- [x] Existing user and RBAC seeding is present
- [x] Existing chat demo seeder is present
- [x] Adapt existing user and RBAC seeding to tenants
- [x] Adapt the existing chat demo seeder to tenants
- [x] Create `CoreSeeder`
- [x] Create `DemoSeeder`
- [x] Create `TestSeeder`
- [x] Create `PerformanceSeeder`

## 10.2 CoreSeeder

- [x] System permissions
- [x] Platform roles
- [x] Tenant default roles
- [x] Role-permission assignments
- [ ] Default system settings
- [ ] Status dictionaries

## 10.3 DemoSeeder

- [x] Platform Super Admin
- [x] Platform Support user
- [x] Main active tenant
- [x] Second active tenant
- [x] Suspended tenant
- [x] Tenant owners
- [x] Tenant administrators
- [x] Telephony managers
- [x] Team managers
- [x] Billing managers
- [x] Analysts
- [x] Agents
- [x] Read-only users
- [x] Existing chat demo data adapted to tenants
- [ ] Activity logs
- [ ] Notifications
- [ ] Dashboard demo data

## 10.4 Seeder Requirements

- [ ] Fixed random seed
- [ ] Reproducible output
- [ ] Stable demo credentials
- [ ] Dates relative to the current date
- [ ] Safe repeated execution where practical
- [ ] No empty primary dashboard widgets
- [ ] Second tenant data for isolation testing
- [ ] Clear separation between demo and test data

---

# 11. Stage 7: Adapt Existing Chat to Multi-Tenancy

Stage status: `COMPLETE`

## 11.1 Backend Adaptations

- [x] Add tenant ownership to conversations
- [x] Add tenant-aware participant validation
- [x] Add tenant-aware message queries
- [x] Add tenant-aware attachment storage
- [x] Add tenant-aware realtime channels
- [x] Add tenant-aware presence
- [x] Add tenant-aware typing events
- [x] Add tenant-aware moderation
- [x] Adapt existing policies
- [x] Adapt existing tests
- [x] Add cross-tenant chat tests

## 11.2 Angular Adaptations

- [x] Existing chat UI is preserved
- [x] Existing conversation selection is preserved
- [x] Existing realtime foundation is preserved
- [x] Existing unread indicator foundation is preserved
- [ ] Fix only verified existing UI issues
- [x] Add tenant context
- [ ] Add permission-aware actions
- [ ] Add reusable chat-header action slots
- [ ] Prepare a call-button integration point
- [ ] Prepare a group-call integration point

## 11.3 Chat Extensions

- [ ] Link conversations to calls
- [ ] Link conversations to conference rooms
- [ ] Add call event messages
- [ ] Add missed-call messages
- [ ] Add conference invitation messages
- [ ] Add permission-controlled recording links
- [ ] Add conference room chat

## 11.4 Validation

- [x] Enforce required tenant ownership on conversations
- [x] Enforce required tenant ownership on messages
- [x] Verify zero orphaned chat tenant rows
- [x] Verify message and conversation tenant consistency
- [x] Validate live chat backfill without data loss
- [x] Verify chat behavior manually across tenant switching
- [x] Adapt Vue chat monitoring to clear state on tenant change
- [x] Re-run targeted Stage 7 backend chat verification after tenant ownership enforcement
- [x] Re-run tenant and RBAC regression verification after tenant-aware chat enforcement
- [x] Re-run the complete backend suite after Stage 7 enforcement changes

---

# 12. Stage 8: Shared Telephony Contracts

- [x] `TelephonyProvider`
- [x] `TelephonyHealthProvider`
- [x] `EndpointProvisioningProvider`
- [ ] `PhoneNumberProvider`
- [x] `CallControlProvider`
- [x] `ConferenceControlProvider`
- [ ] `RecordingProvider`
- [ ] TelephonyEventProvider
- [x] Provider capability model
- [x] Normalized provider DTOs
- [x] Normalized provider exceptions
- [x] Idempotency rules
- [ ] Integration-event persistence
- [ ] Retry rules
- [ ] Dead-letter state
- [ ] Manual replay foundation

The Laravel domain must not depend directly on FreeSWITCH-specific classes.

---

# 13. Stage 9: Fake PBX Adapter

- [x] Simulate extension registration
- [ ] Simulate incoming calls
- [x] Simulate outgoing calls
- [ ] Simulate ringing
- [x] Simulate answer
- [ ] Simulate decline
- [ ] Simulate busy
- [x] Simulate failure
- [x] Simulate hangup
- [x] Simulate hold and resume
- [x] Simulate transfer
- [x] Simulate conference creation
- [x] Simulate participant invitations
- [ ] Simulate participant joins and leaves
- [ ] Simulate recordings
- [ ] Simulate duplicate events
- [ ] Simulate out-of-order events
- [x] Use it in automated tests
- [ ] Use it in the default demo environment

---

# 14. Stage 10: Contacts

- [x] Company contacts
- [x] Personal contacts
- [x] Contact phone numbers
- [x] Contact email addresses
- [x] Tags
- [x] Notes
- [ ] Favorites
- [ ] Unified directory
- [ ] Tenant user directory
- [ ] Extension directory
- [ ] Recent-call contacts
- [x] Search
- [x] Import
- [x] Export
- [x] Permission-aware visibility
- [ ] Private-contact isolation
- [x] Demo contact seed data

---

# 15. Stage 11: Extensions and Phone Numbers

## 15.1 Extensions

- [x] Extension model
- [x] Tenant ownership
- [x] Number
- [x] Display name
- [x] Status
- [x] Assigned user
- [x] External PBX identifier
- [x] Provider metadata
- [x] Unique number per tenant
- [x] CRUD
- [x] Assignment
- [x] Activation and suspension
- [x] Policies
- [ ] Events
- [ ] Activity logs
- [x] API documentation
- [x] Tests
- [x] Demo data
- [x] SIP credential generation
- [x] Encrypted credential storage
- [x] Credential rotation
- [x] Extension provisioning state
- [x] Fake-provider extension provisioning
- [x] Extension permissions
- [x] Extension API
- [x] Angular extensions UI
- [x] Tenant-aware extension seed data
- [x] Extension isolation tests

## 15.2 Phone Numbers and DIDs

- [x] Phone number model
- [x] Tenant ownership
- [x] E.164 normalization
- [x] Provider
- [x] External identifier
- [x] Status
- [x] Capabilities
- [ ] Cost metadata
- [ ] Routing destination
- [x] CRUD
- [x] Policies
- [ ] Events
- [ ] Activity logs
- [x] API documentation
- [x] Tests
- [x] Demo data
- [x] DID assignment to users
- [x] Multiple DIDs per user
- [x] One primary DID per user per tenant
- [x] Tenant-scoped DID inventory
- [x] DID permissions
- [x] DID API
- [x] Angular DID UI
- [x] Tenant-aware DID seed data
- [x] DID isolation tests
- [x] Primary DID resolver
- [x] Inbound DID ownership resolver foundation

---

# 16. Stage 12: Call Logs and Statistics

- [x] Call log model
- [x] External call identifier
- [x] Direction
- [x] Status
- [x] Disposition
- [x] Source
- [x] Destination
- [x] Normalized numbers
- [x] Extension relation
- [x] DID relation
- [x] Start time
- [x] Ringing time
- [x] Answer time
- [x] End time
- [x] Duration
- [x] Billable duration
- [x] Hangup cause
- [x] Provider metadata
- [x] Correlation ID
- [x] Duplicate-event protection
- [x] Out-of-order event handling
- [x] Filters
- [x] Search
- [x] Pagination
- [x] Export
- [x] Own/team/tenant visibility
- [x] API documentation
- [x] Tests

## 16.1 Demo Call Data

- [x] Generate at least 1,000 reproducible call records
- [x] Spread records across the previous 30–90 days
- [x] Include incoming calls
- [x] Include outgoing calls
- [x] Include internal calls
- [x] Include answered calls
- [x] Include missed calls
- [x] Include busy calls
- [x] Include declined calls
- [x] Include failed calls
- [x] Include conference calls
- [x] Populate all initial call-statistics charts

---

# 17. Stage 13: Telephony Routing

- [x] Ring groups
- [x] Ring group members
- [x] Simultaneous strategy
- [x] Sequential strategy
- [x] Random strategy
- [x] Call queues
- [x] Queue members
- [x] Queue strategies
- [x] Agent pause and resume
- [x] Queue overflow destinations
- [x] IVR
- [x] IVR options
- [x] Timeout actions
- [x] Invalid-input actions
- [x] Route validation
- [x] Route-loop validation
- [x] Policies
- [x] Events
- [x] Tests
- [x] Demo data

---

# 17.1 Backend Test Database Stabilization

Several telephony feature slices have backend functional tests that cannot be fully trusted until the isolated testing database bootstrap is stable.

- [x] Rebuild or reset the `saas_testing` database bootstrap
- [x] Verify migrations run cleanly from an empty testing database
- [x] Rerun Ring Groups backend feature tests
- [x] Rerun Call Queues backend feature tests
- [x] Rerun IVR backend feature tests
- [x] Rerun SeederArchitectureTest
- [ ] Rerun the full backend test suite
- [x] Update baseline validation docs with trusted backend totals

---

# 18. Stage 14: FreeSWITCH Docker Profile

Stage status: `COMPLETE`

- [x] Optional Docker Compose FreeSWITCH profile
- [x] Local FreeSWITCH service boots
- [x] SIP ports documented
- [x] RTP media range documented
- [x] Event Socket security documented
- [x] FreeSWITCH env placeholders added
- [x] Fake PBX remains default provider
- [x] FreeSWITCH health/smoke commands documented
- [x] FreeSWITCH config volume documented
- [x] Tests do not require FreeSWITCH

Note: The selected local FreeSWITCH image is `servicebots/freeswitch:latest`, which boots cleanly in this environment. Stage 15.6 adds the DB-backed provisioning test harness and keeps the live smoke script optional/manual rather than part of the default backend suite. The previously tried `safarov/freeswitch:1.10.12` image left the container unhealthy here and should not be used unless it is revalidated end-to-end.

Target command:

```bash
docker compose --profile freeswitch up -d
```

---

# 19. Stage 15: Angular Call Control Layer

Do not place SIP.js logic inside existing chat components.

Create a reusable Angular call-control layer:

```text
CallControlModule
SipClientService
SoftphoneModal
CallControlApiService
```

- [x] SIP.js integration
- [x] Tenant-scoped SIP profile endpoint
- [x] Call-control permissions
- [x] Angular softphone button
- [x] Angular softphone modal
- [x] Registration status
- [x] Microphone permission handling
- [x] Remote audio element handling
- [x] Local/demo SIP credential gate
- [x] Backend SIP profile can enable local demo registration
- [x] Local-only SIP WS fallback for browser trust failures
- [x] FreeSWITCH demo user provisioning documented/scripted
- [x] FreeSWITCH demo directory provisioning resolves `1001` and `1002` in the live container domain
- [x] FreeSWITCH browser-domain alias resolves `1001`, `1002`, `2001`, and `2002` under `localhost`
- [x] Angular register button gated by credential availability
- [x] Angular SIP.js registration attempt wired for local demo mode
- [x] Clear registration failure message for local WSS/TLS issues
- [x] SIP password stays memory-only and is cleared on tenant switch/failure
- [x] Outbound call is gated on registration state
- [x] Local SIP URI helper builds `sip:1002@localhost` style targets
- [x] Two-browser extension picker is available in the softphone modal
- [x] Tests
- [ ] Browser registration for 1001
- [ ] Browser registration for 1002
- [ ] Two-browser call verification `1001 -> 1002`
- [ ] Remote audio verification
- [ ] SIP registration
- [ ] WSS connection
- [ ] WebRTC audio
- [ ] Incoming calls
- [ ] Outgoing calls
- [ ] Accept
- [ ] Decline
- [ ] Hang up
- [ ] Mute and unmute
- [ ] Hold and resume
- [ ] Transfer
- [ ] DTMF
- [ ] Device selection
- [ ] Reconnect behavior
- [ ] Error handling
- [ ] Floating minimized mode
- [x] Permission-aware controls
- [x] Tenant switch cleanup
- [x] Logout cleanup
- [x] Tests
- [ ] Browser verification

Note: FreeSWITCH runtime readiness and demo provisioning for `1001` and `1002` were verified in Docker. Local SIP transport now falls back to `ws://localhost:5066` in local demo mode when the browser rejects the self-signed WSS certificate. The new `localhost` browser-domain alias is provisioned separately, and the provisioning script now generates a temporary runtime-domain XML copy plus a local demo dialplan fixture so auth can resolve `1001`, `1002`, `2001`, and `2002` under both browser and runtime domains. The softphone now leaves the remote audio element unmuted and surfaces media diagnostics for playback issues. If the FreeSWITCH container is recreated, the runtime-copied XML and browser registrations are cleared and provisioning must be run again before manual browser registration. Live browser registration and `1001 -> 1002` call verification remain partial in this workspace because the in-app browser-control bridge is unavailable here.

The same call-control service must be reusable from:

- chat;
- contacts;
- users;
- extensions;
- recent calls;
- call logs;
- conference participants.

## 19.1 Vue Admin SIP.js Softphone

Vue Admin should include a real SIP.js/WebRTC softphone so platform admins and support users can place calls directly from the admin interface.

- [ ] Add SIP.js dependency to the Vue admin frontend
- [ ] Create Vue SIP client service
- [ ] Create reusable Vue softphone modal
- [ ] Add call button to the platform admin dashboard
- [ ] Add call button to tenant-support telephony pages where useful
- [ ] Support SIP registration from selected tenant/extension credentials
- [ ] Show registration state: disconnected, connecting, registered, failed
- [ ] Add destination input for extension or phone number
- [ ] Start outbound audio call from Vue Admin
- [ ] Show call states: idle, dialing, ringing, active, held, ended, failed
- [ ] Add answer, reject, hangup controls
- [ ] Add mute and unmute microphone
- [ ] Add hold and resume placeholders if backend/PBX support is not ready yet
- [ ] Add device permission checks for microphone
- [ ] Add audio output element for remote media
- [ ] Add local microphone stream handling
- [ ] Clean up media tracks after call end
- [ ] Keep SIP credentials tenant-scoped
- [ ] Do not expose SIP passwords in logs, UI, or Vue devtools-friendly state
- [ ] Store only safe SIP account metadata in frontend state
- [ ] Add permission-aware visibility for platform admin/support users
- [ ] Add useful English comments in SIP service, media handling, and security-sensitive areas
- [ ] Add Vue tests for modal state, registration state, permission visibility, and call controls
- [ ] Document Vue Admin SIP.js softphone setup and limitations

## Shared Softphone Rules

- [ ] Keep Angular and Vue SIP clients behavior-compatible
- [ ] Use the same call state names in Angular and Vue
- [ ] Use the same media cleanup rules in Angular and Vue
- [ ] Use the same tenant-scoped SIP credential contract
- [ ] Document differences between tenant softphone and admin/support softphone

## 15.5 Browser SIP Domain and WSS Alignment

- [x] Separate browser SIP domain from FreeSWITCH directory lookup domain
- [x] Keep Angular SIP URIs browser-facing
- [x] Keep provisioning verification runtime-domain-aware
- [x] Document WSS/TLS browser trust limitation
- [x] Confirm CallControl backend tests
- [x] Confirm Angular tests if frontend changes

## 15.6 FreeSWITCH DB-Backed Provisioning Test Harness

Stage status: `PARTIAL`

- [x] Add Laravel FreeSWITCH directory XML contract tests
- [x] Add Laravel FreeSWITCH dialplan XML contract tests
- [x] Add tenant-isolation provisioning security tests
- [x] Add reusable XML assertions for FreeSWITCH payloads
- [x] Add optional live FreeSWITCH smoke-test script
- [x] Document the FreeSWITCH testing strategy
- [x] Keep live FreeSWITCH smoke tests outside the default backend tests
- [x] Keep static XML users local-demo fallback only
- [x] Keep Laravel DB-backed provisioning as the target architecture
- PARTIAL Run the optional live smoke script in this workspace

## 15.7 Stable FreeSWITCH Container and DB-backed Directory Scaffold

Stage status: `COMPLETE`

- [x] Add stable FreeSWITCH container name
- [x] Document one-time cleanup of old generated container
- [x] Scaffold Laravel-backed FreeSWITCH directory endpoint
- [x] Generate parseable directory XML from DB extensions
- [x] Gate endpoint by FreeSWITCH/local config
- [x] Keep password local-demo gated
- [x] Add tenant isolation tests
- [x] Keep static XML users local-demo fallback only
- [x] Document future mod_xml_curl wiring
- [x] Tests
---

# 20. Stage 16: Calling from Chat

## 20.1 Direct Chat

- [ ] Add an audio-call button to the existing chat header
- [ ] Hide the button without permission
- [ ] Resolve the participant extension
- [ ] Open the reusable softphone modal
- [ ] Start the call through `CallControlService`
- [ ] Add a call-started event message
- [ ] Add a missed-call event message
- [ ] Add a completed-call summary

## 20.2 Group Chat

- [ ] Add a group-call button
- [ ] Allow participant selection
- [ ] Create an ad-hoc conference room
- [ ] Invite selected participants
- [ ] Add a join-call message
- [ ] Show realtime participant states
- [ ] Store conference lifecycle messages

---

# 21. Stage 17: Conference Rooms

- [ ] Permanent rooms
- [ ] Scheduled rooms
- [ ] Ad-hoc rooms
- [ ] Private rooms
- [ ] Team rooms
- [ ] Tenant-wide rooms
- [ ] Maximum participant limits
- [ ] Moderator roles
- [ ] Waiting room option
- [ ] PIN support
- [ ] Recording option
- [ ] Security mode
- [ ] Conference policies
- [ ] Conference API
- [ ] Conference history
- [ ] Conference chat
- [ ] Demo conference data

## 21.1 Convert an Active Call to a Conference

- [ ] Add participant button
- [ ] Create an ad-hoc conference
- [ ] Move active call legs into the room
- [ ] Keep current participants connected
- [ ] Invite a tenant user
- [ ] Invite an extension
- [ ] Invite a company contact
- [ ] Invite a private contact
- [ ] Invite an external number
- [ ] Allow authorized internal participants to invite others
- [ ] Keep private contact records private
- [ ] Track who invited each participant
- [ ] Update participant statuses in realtime

---

# 22. Stage 18: Call Security

Required from the first real VoIP version:

- [ ] HTTPS
- [ ] WSS
- [ ] TLS
- [ ] DTLS-SRTP
- [ ] Encrypted SIP credentials
- [ ] Encrypted PBX credentials
- [ ] Private recording storage
- [ ] Signed recording URLs
- [ ] Sensitive-log redaction

## 22.1 Security Modes

```text
standard
secure_transport
end_to_end
```

Initial release:

- [ ] `secure_transport`

Future release:

- [ ] Internal one-to-one E2EE calls
- [ ] E2EE provider abstraction
- [ ] Recording disabled in E2EE
- [ ] Incompatible server media features disabled
- [ ] External SIP restrictions
- [ ] Downgrade warnings
- [ ] Security-state indicator in Angular

---

# 23. Stage 19: Recordings

- [ ] Call recording metadata
- [ ] Conference recording metadata
- [ ] Private storage
- [ ] Signed access URLs
- [ ] Listen permission
- [ ] Download permission
- [ ] Delete permission
- [ ] Retention policy
- [ ] Cleanup job
- [ ] Download audit
- [ ] Delete audit
- [ ] Tenant isolation tests

---

# 24. Stage 20: Dashboard Expansion

## 24.1 Angular Tenant Dashboard

- [ ] Calls today
- [ ] Active calls
- [ ] Answered calls
- [ ] Missed calls
- [ ] Average duration
- [ ] Calls by day
- [ ] Calls by hour
- [ ] Calls by extension
- [ ] Calls by DID
- [ ] Queue statistics
- [ ] Active conferences
- [ ] Conference participants
- [ ] Chat activity
- [ ] Unread messages
- [ ] Usage cost
- [ ] Recording storage

## 24.2 Vue Platform Dashboard

- [ ] Active tenants
- [ ] Suspended tenants
- [ ] Active users
- [ ] Active PBX connections
- [ ] Queue depth
- [ ] Failed jobs
- [ ] Reverb health
- [ ] Redis health
- [ ] Database health
- [ ] FreeSWITCH health
- [ ] Active calls
- [ ] Active conferences
- [ ] Platform storage usage

## 24.3 Dashboard Rules

- [ ] Every widget has a permission
- [ ] Hidden widgets leave no empty placeholders
- [ ] Widget API endpoints enforce permissions
- [ ] DemoSeeder populates every primary chart
- [ ] Tenant isolation applies to all statistics

---

# 25. Stage 21: Webhooks

- [ ] Extend the existing event and queue foundation
- [ ] Webhook endpoints
- [ ] Webhook subscriptions
- [ ] Signing secrets
- [ ] Signature validation
- [ ] Replay protection
- [ ] Async delivery
- [ ] Retry and backoff
- [ ] Delivery logs
- [ ] Manual redelivery
- [ ] Dead-letter state
- [ ] Metrics
- [ ] Tests

---

# 26. Stage 22: Billing and Usage

Do not treat the existing billing page as a completed telephony billing implementation.

- [ ] Billing accounts
- [ ] Rate plans
- [ ] Rates
- [ ] Immutable usage records
- [ ] Money value object
- [ ] Call usage
- [ ] Conference usage
- [ ] External participant usage
- [ ] Recording storage usage
- [ ] Rating service
- [ ] Rate version
- [ ] Rerating flow
- [ ] Balance checks
- [ ] Credit limits
- [ ] Billing audit
- [ ] Tests
- [ ] Demo billing data

---

# 27. Stage 23: Reports

- [ ] Calls report
- [ ] Missed calls report
- [ ] Duration report
- [ ] Extension usage
- [ ] DID usage
- [ ] Queue performance
- [ ] Conference report
- [ ] Participant report
- [ ] External call cost report
- [ ] Chat activity report
- [ ] Recording storage report
- [ ] Billing report
- [ ] CSV export
- [ ] Async generation
- [ ] Download authorization
- [ ] Report expiration
- [ ] Tenant timezone support
- [ ] Tests

---

# 28. Stage 24: Monitoring Expansion

- [ ] Scheduler health
- [ ] Reverb health
- [ ] Horizon health
- [ ] FreeSWITCH health
- [ ] SIP registration count
- [ ] Active calls
- [ ] Active conferences
- [ ] PBX integration failures
- [ ] Webhook delivery failures
- [ ] Recording storage
- [ ] Tenant-level metrics
- [ ] Platform-level metrics
- [ ] Vue monitoring UI
- [ ] Alerting rules

---

# 29. Stage 25: Testing and Security

## 29.1 Required New Test Areas

- [ ] Tenant isolation
- [ ] Tenant-aware RBAC
- [ ] Tenant-aware chat
- [ ] Tenant-aware realtime
- [ ] Tenant-aware queues
- [ ] Telephony contracts
- [ ] Fake PBX
- [ ] FreeSWITCH integration
- [ ] Call control
- [ ] Calls from chat
- [ ] Conference conversion
- [ ] Participant invitations
- [ ] Private contacts
- [ ] Recordings
- [ ] Webhooks
- [ ] Billing
- [ ] Reports
- [ ] Security modes

## 29.2 Security Review

- [ ] Privilege escalation
- [ ] Cross-tenant data access
- [ ] Cross-tenant realtime access
- [ ] Credential encryption
- [ ] Contact privacy
- [ ] Attachment security
- [ ] Recording security
- [ ] External call permissions
- [ ] Conference permissions
- [ ] Webhook SSRF protection
- [ ] Rate limiting
- [ ] Sensitive queue payloads
- [ ] Sensitive log redaction

---

# 30. Stage 26: Documentation

- [x] Rename existing documentation branding
- [x] Preserve useful existing documentation
- [x] Create baseline validation report
- [x] Update the architecture overview
- [x] Add tenant architecture
- [ ] Add RBAC matrix
- [ ] Add permission catalog
- [ ] Add telephony domain model
- [ ] Add PBX integration guide
- [ ] Add FreeSWITCH Docker guide
- [ ] Add chat-to-call sequence
- [ ] Add call-to-conference sequence
- [ ] Add security-mode documentation
- [ ] Add seed-data guide
- [ ] Add demo credentials
- [ ] Add monitoring guide
- [ ] Add known limitations
- [ ] Add roadmap

---

# 31. Immediate Execution Order

## Step 1: Complete Git Baseline

- [x] Run Git ignore verification
- [x] Initialize Git
- [x] Set the default branch to `main`
- [x] Review staged files
- [x] Create the initial baseline commit

## Step 2: Complete Remaining Baseline Checks

- [x] Run the complete backend test suite
- [x] Verify live browser login
- [x] Verify direct chat between two users
- [x] Verify group chat
- [x] Verify realtime delivery
- [x] Verify permission-denied behavior
- [x] Verify Horizon runtime
- [x] Add and verify scheduler runtime
- [x] Resolve or document non-blocking frontend warnings

## Step 3: Finalize Architecture Decisions

- [x] Add required ADRs
- [x] Document module-extension rules
- [x] Approve the multi-tenancy implementation plan

## Step 4: Implement Multi-Tenancy

- [x] Tenant
- [x] TenantMembership
- [x] TenantContext
- [x] Tenant-aware RBAC
- [x] Tenant-aware seeders
- [x] Tenant-aware chat
- [x] Tenant isolation tests

## Step 5: Begin Telephony Foundation

- [x] Shared telephony contracts
- [x] Fake PBX adapter
- [x] Contacts
- [x] Extensions
- [x] Phone numbers
- [x] Call logs

---

# 32. Milestone 1: Renamed and Running Baseline

Milestone status: `COMPLETE`

Completed:

- [x] Project is renamed to `multi-tenant-telephony-platform`
- [x] Source-controlled obsolete branding is removed
- [x] Existing Laravel backend remains intact
- [x] Existing API routes load
- [x] Angular builds successfully
- [x] Angular tests pass
- [x] Vue builds successfully
- [x] Vue tests pass
- [x] Existing chat foundation remains intact
- [x] Existing RBAC foundation remains intact
- [x] Docker Compose configuration is valid
- [x] Core containers are running
- [x] Existing migrations complete
- [x] Existing seeders complete
- [x] Baseline validation is documented
- [x] Git ignore rules cover sensitive and generated files

Still required before closing Milestone 1:

- [x] Run the complete backend test suite
- [x] Verify browser authentication
- [x] Verify browser chat and realtime
- [x] Verify Horizon runtime
- [x] Verify scheduler runtime

---

# 33. Milestone 2: Multi-Tenant Foundation

Milestone status: `PARTIAL`

- [x] Tenants exist
- [x] Tenant memberships exist
- [x] Tenant switching works
- [x] TenantContext works
- [x] Platform and tenant roles are separated
- [x] Custom tenant roles work
- [x] Existing chat is tenant-isolated
- [x] Existing realtime channels are tenant-isolated
- [ ] Existing notifications are tenant-aware
- [ ] Existing activity logs are tenant-aware
- [x] CoreSeeder works
- [x] DemoSeeder creates multiple tenants
- [ ] Dashboard widgets are not empty
- [x] Cross-tenant security tests pass
- [ ] No telephony code bypasses TenantContext or RBAC

---

# 34. Milestone 3: Telephony Foundation

Milestone status: `PARTIAL`

- [x] Shared PBX contracts exist
- [x] Fake PBX adapter works
- [x] Contacts work
- [x] Extensions work
- [x] Phone numbers and DIDs work
- [x] Call logs work
- [x] Demo telephony data exists
- [x] Initial telephony statistics are populated
- [x] Telephony permissions work
- [x] Telephony APIs are documented
