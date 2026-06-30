<?php

namespace App\Services\Ivr;

use App\Enums\Ivr\IvrActionType;
use App\Enums\Ivr\IvrDestinationType;
use App\Exceptions\Telephony\TelephonyConflictException;
use App\Events\Ivr\IvrMenuCreated;
use App\Events\Ivr\IvrMenuDeleted;
use App\Events\Ivr\IvrMenuUpdated;
use App\Events\Ivr\IvrOptionChanged;
use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\Ivr\IvrMenuStatus;
use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\Extension;
use App\Models\IvrMenu;
use App\Models\IvrOption;
use App\Models\RingGroup;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IvrMenuService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IvrRoutingService $routingService,
    ) {
    }

    public function create(array $payload, User $actor): IvrMenu
    {
        return DB::transaction(function () use ($payload, $actor): IvrMenu {
            $tenantId = $this->requireTenantId();
            $name = trim((string) $payload['name']);
            $slug = $this->normalizeSlug($payload['slug'] ?? null, $name);
            $this->assertUniqueSlug($tenantId, $slug);
            $this->assertDestinationValid($tenantId, $payload['timeout_destination_type'] ?? null, $payload['timeout_destination_id'] ?? null);
            $this->assertDestinationValid($tenantId, $payload['invalid_destination_type'] ?? null, $payload['invalid_destination_id'] ?? null);

            $menu = IvrMenu::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? IvrMenuStatus::Active->value,
                'greeting_text' => $payload['greeting_text'] ?? null,
                'greeting_audio_path' => $payload['greeting_audio_path'] ?? null,
                'repeat_count' => (int) ($payload['repeat_count'] ?? 1),
                'input_timeout_seconds' => (int) ($payload['input_timeout_seconds'] ?? 5),
                'max_invalid_attempts' => (int) ($payload['max_invalid_attempts'] ?? 3),
                'timeout_action_type' => $payload['timeout_action_type'] ?? IvrActionType::Repeat->value,
                'timeout_destination_type' => $payload['timeout_destination_type'] ?? null,
                'timeout_destination_id' => $payload['timeout_destination_id'] ?? null,
                'invalid_action_type' => $payload['invalid_action_type'] ?? IvrActionType::Repeat->value,
                'invalid_destination_type' => $payload['invalid_destination_type'] ?? null,
                'invalid_destination_id' => $payload['invalid_destination_id'] ?? null,
                'settings' => $payload['settings'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            event(new IvrMenuCreated($menu));

            return $menu;
        });
    }

    public function update(IvrMenu $ivrMenu, array $payload, User $actor): IvrMenu
    {
        return DB::transaction(function () use ($ivrMenu, $payload, $actor): IvrMenu {
            $target = IvrMenu::query()->whereKey($ivrMenu->getKey())->lockForUpdate()->firstOrFail();
            $tenantId = (string) $target->tenant_id;
            $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : $target->name;
            $slug = $this->normalizeSlug($payload['slug'] ?? $target->slug, $name);
            $this->assertUniqueSlug($tenantId, $slug, $target);
            $this->assertDestinationValid($tenantId, $payload['timeout_destination_type'] ?? $target->timeout_destination_type, $payload['timeout_destination_id'] ?? $target->timeout_destination_id, $target);
            $this->assertDestinationValid($tenantId, $payload['invalid_destination_type'] ?? $target->invalid_destination_type, $payload['invalid_destination_id'] ?? $target->invalid_destination_id, $target);

            $target->forceFill([
                'name' => $name,
                'slug' => $slug,
                'description' => $payload['description'] ?? $target->description,
                'status' => $payload['status'] ?? ($target->status?->value ?? $target->status),
                'greeting_text' => array_key_exists('greeting_text', $payload) ? $payload['greeting_text'] : $target->greeting_text,
                'greeting_audio_path' => array_key_exists('greeting_audio_path', $payload) ? $payload['greeting_audio_path'] : $target->greeting_audio_path,
                'repeat_count' => $payload['repeat_count'] ?? $target->repeat_count,
                'input_timeout_seconds' => $payload['input_timeout_seconds'] ?? $target->input_timeout_seconds,
                'max_invalid_attempts' => $payload['max_invalid_attempts'] ?? $target->max_invalid_attempts,
                'timeout_action_type' => $payload['timeout_action_type'] ?? ($target->timeout_action_type?->value ?? $target->timeout_action_type),
                'timeout_destination_type' => array_key_exists('timeout_destination_type', $payload) ? $payload['timeout_destination_type'] : $target->timeout_destination_type,
                'timeout_destination_id' => array_key_exists('timeout_destination_id', $payload) ? $payload['timeout_destination_id'] : $target->timeout_destination_id,
                'invalid_action_type' => $payload['invalid_action_type'] ?? ($target->invalid_action_type?->value ?? $target->invalid_action_type),
                'invalid_destination_type' => array_key_exists('invalid_destination_type', $payload) ? $payload['invalid_destination_type'] : $target->invalid_destination_type,
                'invalid_destination_id' => array_key_exists('invalid_destination_id', $payload) ? $payload['invalid_destination_id'] : $target->invalid_destination_id,
                'settings' => $payload['settings'] ?? $target->settings ?? [],
                'metadata' => $payload['metadata'] ?? $target->metadata ?? [],
                'updated_by' => $actor->getKey(),
            ])->save();

            event(new IvrMenuUpdated($target->fresh()));

            return $target->fresh(['options']);
        });
    }

    public function delete(IvrMenu $ivrMenu): void
    {
        DB::transaction(function () use ($ivrMenu): void {
            event(new IvrMenuDeleted($ivrMenu));
            $ivrMenu->delete();
        });
    }

    public function createOption(IvrMenu $ivrMenu, array $payload): IvrOption
    {
        return DB::transaction(function () use ($ivrMenu, $payload): IvrOption {
            $this->assertMenuTenant($ivrMenu);
            $this->assertUniqueDigit($ivrMenu, $payload['digit']);
            $this->assertDestinationValid($ivrMenu->tenant_id, $payload['destination_type'] ?? null, $payload['destination_id'] ?? null, $ivrMenu);

            $option = IvrOption::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $ivrMenu->tenant_id,
                'ivr_menu_id' => $ivrMenu->getKey(),
                'digit' => strtoupper(trim((string) $payload['digit'])),
                'label' => trim((string) $payload['label']),
                'destination_type' => $payload['destination_type'],
                'destination_id' => $payload['destination_id'] ?? null,
                'priority' => (int) ($payload['priority'] ?? 1),
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'metadata' => $payload['metadata'] ?? [],
            ]);

            event(new IvrOptionChanged($option));

            return $option->fresh();
        });
    }

    public function updateOption(IvrMenu $ivrMenu, IvrOption $option, array $payload): IvrOption
    {
        return DB::transaction(function () use ($ivrMenu, $option, $payload): IvrOption {
            $this->assertMenuTenant($ivrMenu);
            $this->assertOptionTenant($ivrMenu, $option);
            $digit = array_key_exists('digit', $payload) ? strtoupper(trim((string) $payload['digit'])) : $option->digit;
            $this->assertUniqueDigit($ivrMenu, $digit, $option);
            $destinationType = array_key_exists('destination_type', $payload) ? $payload['destination_type'] : $option->destination_type;
            $destinationId = array_key_exists('destination_id', $payload) ? $payload['destination_id'] : $option->destination_id;
            $this->assertDestinationValid($ivrMenu->tenant_id, $destinationType, $destinationId, $ivrMenu);

            $option->forceFill([
                'digit' => $digit,
                'label' => array_key_exists('label', $payload) ? trim((string) $payload['label']) : $option->label,
                'destination_type' => $destinationType,
                'destination_id' => $destinationId,
                'priority' => $payload['priority'] ?? $option->priority,
                'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : $option->is_active,
                'metadata' => $payload['metadata'] ?? $option->metadata ?? [],
            ])->save();

            event(new IvrOptionChanged($option->fresh()));

            return $option->fresh();
        });
    }

    public function deleteOption(IvrMenu $ivrMenu, IvrOption $option): void
    {
        DB::transaction(function () use ($ivrMenu, $option): void {
            $this->assertMenuTenant($ivrMenu);
            $this->assertOptionTenant($ivrMenu, $option);
            event(new IvrOptionChanged($option));
            $option->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function testRoute(IvrMenu $ivrMenu, array $payload): array
    {
        $inputType = (string) ($payload['input_type'] ?? 'digit');
        $digit = $payload['digit'] ?? null;

        return $this->routingService->resolve($ivrMenu->fresh(['options']), is_string($digit) ? $digit : null, $inputType);
    }

    private function requireTenantId(): string
    {
        return (string) $this->tenantContext->requireTenant()->getKey();
    }

    private function assertMenuTenant(IvrMenu $menu): void
    {
        if ((string) $menu->tenant_id !== (string) $this->tenantContext->requireTenant()->getKey()) {
            abort(404, 'IVR menu not found.');
        }
    }

    private function assertOptionTenant(IvrMenu $menu, IvrOption $option): void
    {
        if ((string) $option->tenant_id !== (string) $menu->tenant_id || (string) $option->ivr_menu_id !== (string) $menu->getKey()) {
            abort(404, 'IVR option not found.');
        }
    }

    private function normalizeSlug(mixed $slug, string $name): string
    {
        $candidate = trim((string) $slug);
        return $candidate !== '' ? Str::slug($candidate) : Str::slug($name);
    }

    private function assertUniqueSlug(string $tenantId, string $slug, ?IvrMenu $menu = null): void
    {
        $query = IvrMenu::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($menu instanceof IvrMenu) {
            $query->where('id', '!=', $menu->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('An IVR menu with this slug already exists in the active tenant.');
        }
    }

    private function assertUniqueDigit(IvrMenu $menu, string $digit, ?IvrOption $ignore = null): void
    {
        $query = IvrOption::query()
            ->where('tenant_id', $menu->tenant_id)
            ->where('ivr_menu_id', $menu->getKey())
            ->where('digit', strtoupper(trim($digit)));

        if ($ignore instanceof IvrOption) {
            $query->where('id', '!=', $ignore->getKey());
        }

        if ($query->exists()) {
            throw new TelephonyConflictException('This digit already exists in the IVR menu.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertDestinationValid(string $tenantId, mixed $destinationType, mixed $destinationId, ?IvrMenu $sourceMenu = null): void
    {
        if (! is_string($destinationType) || $destinationType === '') {
            return;
        }

        if (in_array($destinationType, [IvrDestinationType::Hangup->value, IvrDestinationType::VoicemailPlaceholder->value], true)) {
            return;
        }

        if (! is_int($destinationId) && ! ctype_digit((string) $destinationId)) {
            throw new TelephonyConflictException('IVR destination requires a valid destination ID.');
        }

        $destinationId = (int) $destinationId;

        if ($destinationType === IvrDestinationType::Extension->value) {
            $exists = Extension::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->where('status', ExtensionStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('IVR destination extension must belong to the active tenant and be active.');
            }

            return;
        }

        if ($destinationType === IvrDestinationType::RingGroup->value) {
            $exists = RingGroup::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->where('status', RingGroupStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('IVR destination ring group must belong to the active tenant and be active.');
            }

            return;
        }

        if ($destinationType === IvrDestinationType::CallQueue->value) {
            $exists = CallQueue::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->where('status', CallQueueStatus::Active->value)
                ->exists();

            if (! $exists) {
                throw new TelephonyConflictException('IVR destination call queue must belong to the active tenant and be active.');
            }

            return;
        }

        if ($destinationType === IvrDestinationType::IvrMenu->value) {
            $destinationMenu = IvrMenu::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->first();

            if (! $destinationMenu || (($destinationMenu->status instanceof IvrMenuStatus ? $destinationMenu->status : IvrMenuStatus::tryFrom((string) $destinationMenu->status)) !== IvrMenuStatus::Active)) {
                throw new TelephonyConflictException('IVR destination menu must belong to the active tenant and be active.');
            }

            if ($sourceMenu && (int) $sourceMenu->getKey() === $destinationId) {
                throw new TelephonyConflictException('IVR destination cannot point to the same IVR menu.');
            }

            if ($sourceMenu && $this->wouldCreateLoop($sourceMenu, $destinationMenu)) {
                throw new TelephonyConflictException('IVR destination would create a routing loop.');
            }

            return;
        }

        throw new TelephonyConflictException('Unsupported IVR destination type.');
    }

    private function wouldCreateLoop(IvrMenu $sourceMenu, IvrMenu $destinationMenu, array $visited = []): bool
    {
        $destinationKey = (string) $destinationMenu->getKey();
        if (in_array($destinationKey, $visited, true)) {
            return true;
        }

        $visited[] = $destinationKey;

        $edges = collect([
            [$destinationMenu->timeout_action_type, $destinationMenu->timeout_destination_type, $destinationMenu->timeout_destination_id],
            [$destinationMenu->invalid_action_type, $destinationMenu->invalid_destination_type, $destinationMenu->invalid_destination_id],
        ]);

        foreach ($destinationMenu->options()->where('is_active', true)->get() as $option) {
            $edges->push([IvrActionType::Route->value, $option->destination_type, $option->destination_id]);
        }

        foreach ($edges as [$actionType, $destinationType, $destinationId]) {
            if ($actionType !== IvrActionType::Route->value || $destinationType !== IvrDestinationType::IvrMenu->value || ! $destinationId) {
                continue;
            }

            if ((int) $destinationId === (int) $sourceMenu->getKey()) {
                return true;
            }

            $nextMenu = IvrMenu::query()
                ->forTenant($sourceMenu->tenant_id)
                ->whereKey($destinationId)
                ->first();

            if ($nextMenu && $this->wouldCreateLoop($sourceMenu, $nextMenu, $visited)) {
                return true;
            }
        }

        return false;
    }
}
