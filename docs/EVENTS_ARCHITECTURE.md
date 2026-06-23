# Events Architecture Convention

## Purpose
This document defines a minimal cross-module event convention for the backend.
It prevents ad-hoc event/listener growth and reduces side-effect duplication.

## Directory Structure
- `app/Events/<Module>/<DomainEvent>.php`
- `app/Listeners/<Module>/<ListenerName>.php`

Examples:
- `app/Events/Users/UserCreated.php`
- `app/Listeners/Users/LogUserCreatedActivity.php`

## Naming Convention
Event names should describe completed domain facts:
- `UserCreated`
- `UserUpdated`
- `RolePermissionsChanged`
- `PermissionChanged`
- `NotificationQueued`

Listener names should describe one explicit side effect:
- `LogUserCreatedActivity`
- `InvalidatePermissionCache`
- `DispatchUserNotification`
- `BroadcastSystemNotification`

## Event Payload Policy
- Include stable scalar identifiers (for example `userId`, `roleId`, `permissionId`).
- Optional snapshot fields are allowed (`userName`, `userEmail`, `title`) for audit/readability.
- Do not pass sensitive data.
- Do not pass request objects or framework-specific transport objects.
- `actorId` should be nullable for system/background flows.
- `occurredAt` can be added as an immutable timestamp string when needed.

## Event Payload Versioning Policy
- Every domain event payload is a stable contract for listeners.
- Do not introduce breaking payload changes without explicit versioning.
- Adding optional/nullable fields is allowed.
- Renaming/removing/changing existing fields is a breaking change.

### Non-breaking changes
- Add nullable/optional field.
- Add `meta.*` field.
- Add snapshot field (`userName`, `title`) when existing listeners do not depend on it.

### Breaking changes
- Rename field.
- Remove field.
- Change field type.
- Change field meaning/semantics.
- Remove `actorId` or `occurredAt` when listeners rely on them.

### Versioning strategy
- Preferred future option: explicit payload version marker (for example class constant):
  - `public const VERSION = 1;`
- Alternative option for breaking evolution: new event name:
  - `UserCreatedV2`
  - `RolePermissionsChangedV2`

### Compatibility rules
- Listeners should be tolerant to optional fields.
- Do not include sensitive data in payload.
- Do not pass request objects or models with unstable loaded relations.
- Prefer scalar IDs plus safe snapshots.

### Example: UserUpdated v1
- Current stable v1 payload:
  - `userId`, `userName`, `userEmail`, `actorId`, `changedFields`, `occurredAt`
- Non-breaking extension example:
  - add optional `meta` or `source` field.
- Breaking example:
  - rename `changedFields` to `changes` without creating `UserUpdatedV2`.

### New Event Checklist
- Stable required fields defined.
- Sensitive data review completed.
- `actorId` decision documented.
- `occurredAt` decision documented.
- Versioning decision documented (`v1` compatible or `V2` event).
- Event/listener tests added or updated.

## Side Effects Policy
- Move side effects from services to listeners gradually, not in one refactor.
- Activity logging may be extracted listener-by-listener.
- Cache invalidation may be extracted listener-by-listener.
- Email, notifications, and realtime delivery should prefer jobs/queues behind listeners.
- Existing observers stay in place until a dedicated migration step is tested.

## Duplication Risk Policy
- Never duplicate the same activity action in both observer and listener.
- If listener is introduced into an existing observer flow:
  - use a unique action/source marker, or
  - remove/adjust the observer behavior in a separate, tested step.

## Transaction and Consistency Policy
- Avoid dispatching critical side effects before database commit when race conditions are possible.
- For future hardening, prefer `afterCommit` patterns for listeners/jobs that depend on committed state.

## afterCommit Policy
- Critical listeners that mutate shared system state should implement Laravel `ShouldHandleEventsAfterCommit`.
- Current critical examples:
  - `LogUserCreatedActivity`
  - `LogUserUpdatedActivity`
  - `InvalidatePermissionCache`
- WHY:
  - prevents writing side effects for transactions that later roll back;
  - reduces risk of stale cache invalidation before committed RBAC changes are visible.
- Keep listeners synchronous by default unless queueing is explicitly required.
- If queueing is introduced later, use after-commit queue dispatch semantics as well.

## Current Foundation Example
- Domain event: `App\Events\Users\UserCreated`
- Listener: `App\Listeners\Users\LogUserCreatedActivity`
- Registration: `App\Providers\EventServiceProvider::$listen`
- Dispatch point: `UserService::create()`

Additional example:
- Domain event: `App\Events\Rbac\RolePermissionsChanged`
- Listener: `App\Listeners\Rbac\InvalidatePermissionCache`
- Behavior: listener runs with after-commit contract before clearing permission cache

This is the reference pattern for the next incremental domain events.
