<?php

namespace App\Services\Ivr;

use App\Enums\CallQueues\CallQueueStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Enums\Ivr\IvrActionType;
use App\Enums\Ivr\IvrDestinationType;
use App\Enums\Ivr\IvrMenuStatus;
use App\Enums\RingGroups\RingGroupStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\CallQueue;
use App\Models\Extension;
use App\Models\IvrMenu;
use App\Models\IvrOption;
use App\Models\RingGroup;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

class IvrRoutingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * This resolver is intentionally a dry-run planner only.
     * It validates configuration and returns a normalized route plan for a
     * later PBX/media integration stage, but it does not place calls or play
     * audio in this slice.
     *
     * @return array<string, mixed>
     */
    public function resolve(IvrMenu $ivrMenu, ?string $digit = null, string $inputType = 'digit'): array
    {
        $tenant = $this->tenantContext->requireTenant();
        if ((string) $ivrMenu->tenant_id !== (string) $tenant->getKey()) {
            abort(404, 'IVR menu not found.');
        }

        $menu = $ivrMenu->fresh(['options']);
        if (! $menu) {
            abort(404, 'IVR menu not found.');
        }

        $menuStatus = $menu->status instanceof IvrMenuStatus ? $menu->status : IvrMenuStatus::tryFrom((string) $menu->status);
        if ($menuStatus !== IvrMenuStatus::Active) {
            return $this->buildPlan($menu, $inputType, $digit, 'menu_inactive', null, ['IVR menu is inactive and cannot route callers.']);
        }

        $normalizedDigit = $digit !== null ? trim($digit) : null;
        $activeOptions = $menu->options
            ->where('is_active', true)
            ->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($inputType === 'digit' && $normalizedDigit !== null && $normalizedDigit !== '') {
            /** @var IvrOption|null $option */
            $option = $activeOptions->firstWhere('digit', $normalizedDigit);
            if ($option instanceof IvrOption) {
                return $this->routeToOption($menu, $option, $inputType, $normalizedDigit);
            }

            return $this->resolveSpecialAction($menu, $menu->invalid_action_type, $menu->invalid_destination_type, $menu->invalid_destination_id, $inputType, $normalizedDigit, 'invalid_input', ['Pressed digit does not match any active IVR option.']);
        }

        if ($inputType === 'timeout') {
            return $this->resolveSpecialAction($menu, $menu->timeout_action_type, $menu->timeout_destination_type, $menu->timeout_destination_id, $inputType, $normalizedDigit, 'timeout', ['No digit was received before the timeout.']);
        }

        return $this->resolveSpecialAction($menu, $menu->invalid_action_type, $menu->invalid_destination_type, $menu->invalid_destination_id, $inputType, $normalizedDigit, 'invalid_input', ['Invalid IVR input.']);
    }

    private function routeToOption(IvrMenu $menu, IvrOption $option, string $inputType, ?string $digit): array
    {
        $destination = $this->resolveDestination($menu, $option->destination_type, $option->destination_id);

        return $this->buildPlan(
            $menu,
            $inputType,
            $digit,
            'digit',
            $destination,
            [],
            $option,
        );
    }

    private function resolveSpecialAction(
        IvrMenu $menu,
        string $actionType,
        ?string $destinationType,
        ?int $destinationId,
        string $inputType,
        ?string $digit,
        string $reason,
        array $notes = [],
    ): array {
        if ($actionType === IvrActionType::Repeat->value) {
            return $this->buildPlan($menu, $inputType, $digit, $reason, null, array_merge($notes, ['Caller should hear the IVR greeting again.']));
        }

        if ($actionType === IvrActionType::Hangup->value) {
            return $this->buildPlan($menu, $inputType, $digit, $reason, [
                'type' => 'hangup',
                'id' => null,
                'summary' => 'hangup',
            ], array_merge($notes, ['Caller should be disconnected.']));
        }

        $destination = $this->resolveDestination($menu, $destinationType, $destinationId);
        return $this->buildPlan($menu, $inputType, $digit, $reason, $destination, $notes);
    }

    private function resolveDestination(IvrMenu $sourceMenu, ?string $destinationType, ?int $destinationId): ?array
    {
        if ($destinationType === IvrDestinationType::Hangup->value) {
            return [
                'type' => 'hangup',
                'id' => null,
                'summary' => 'hangup',
            ];
        }

        if ($destinationType === IvrDestinationType::VoicemailPlaceholder->value) {
            return [
                'type' => 'voicemail_placeholder',
                'id' => null,
                'summary' => 'voicemail_placeholder',
            ];
        }

        if (! is_string($destinationType) || $destinationType === '' || ! $destinationId) {
            return null;
        }

        $tenantId = (string) $sourceMenu->tenant_id;

        if ($destinationType === IvrDestinationType::Extension->value) {
            $extension = Extension::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->first();

            if (! $extension || (($extension->status instanceof ExtensionStatus ? $extension->status : ExtensionStatus::tryFrom((string) $extension->status)) !== ExtensionStatus::Active)) {
                abort(409, 'IVR destination extension must belong to the active tenant and be active.');
            }

            return [
                'type' => 'extension',
                'id' => $extension->id,
                'summary' => sprintf('Extension: %s%s', $extension->number, $extension->label ? ' - '.$extension->label : ''),
            ];
        }

        if ($destinationType === IvrDestinationType::RingGroup->value) {
            $ringGroup = RingGroup::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->first();

            if (! $ringGroup || ((string) ($ringGroup->status instanceof RingGroupStatus ? $ringGroup->status->value : $ringGroup->status) !== RingGroupStatus::Active->value)) {
                abort(409, 'IVR destination ring group must belong to the active tenant and be active.');
            }

            return [
                'type' => 'ring_group',
                'id' => $ringGroup->id,
                'summary' => sprintf('Ring group: %s', $ringGroup->name),
            ];
        }

        if ($destinationType === IvrDestinationType::CallQueue->value) {
            $queue = CallQueue::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->first();

            if (! $queue || ((string) ($queue->status instanceof CallQueueStatus ? $queue->status->value : $queue->status) !== CallQueueStatus::Active->value)) {
                abort(409, 'IVR destination call queue must belong to the active tenant and be active.');
            }

            return [
                'type' => 'call_queue',
                'id' => $queue->id,
                'summary' => sprintf('Call queue: %s', $queue->name),
            ];
        }

        if ($destinationType === IvrDestinationType::IvrMenu->value) {
            if ((int) $destinationId === (int) $sourceMenu->getKey()) {
                // Self-loops are rejected up front so IVR designers cannot trap
                // callers in a menu that immediately routes back to itself.
                abort(409, 'IVR destination cannot point to the same IVR menu.');
            }

            $destinationMenu = IvrMenu::query()
                ->forTenant($tenantId)
                ->whereKey($destinationId)
                ->first();

            if (! $destinationMenu || (($destinationMenu->status instanceof IvrMenuStatus ? $destinationMenu->status : IvrMenuStatus::tryFrom((string) $destinationMenu->status)) !== IvrMenuStatus::Active)) {
                abort(409, 'IVR destination menu must belong to the active tenant and be active.');
            }

            if ($this->wouldCreateLoop($sourceMenu, $destinationMenu)) {
                // The route graph is intentionally validated conservatively: a
                // nested IVR path that can clearly return to the source menu is
                // rejected so routing remains safe without a full graph engine.
                abort(409, 'IVR destination would create a routing loop.');
            }

            return [
                'type' => 'ivr_menu',
                'id' => $destinationMenu->id,
                'summary' => sprintf('IVR menu: %s', $destinationMenu->name),
            ];
        }

        abort(409, 'Unsupported IVR destination type.');
    }

    private function wouldCreateLoop(IvrMenu $sourceMenu, IvrMenu $destinationMenu, array $visited = []): bool
    {
        $destinationKey = (string) $destinationMenu->getKey();
        if (in_array($destinationKey, $visited, true)) {
            return true;
        }

        $visited[] = $destinationKey;

        $menusToInspect = collect([
            [
                'type' => $destinationMenu->timeout_action_type,
                'destination_type' => $destinationMenu->timeout_destination_type,
                'destination_id' => $destinationMenu->timeout_destination_id,
            ],
            [
                'type' => $destinationMenu->invalid_action_type,
                'destination_type' => $destinationMenu->invalid_destination_type,
                'destination_id' => $destinationMenu->invalid_destination_id,
            ],
        ]);

        /** @var IvrOption $option */
        foreach ($destinationMenu->options()->where('is_active', true)->get() as $option) {
            $menusToInspect->push([
                'type' => 'route',
                'destination_type' => $option->destination_type,
                'destination_id' => $option->destination_id,
            ]);
        }

        foreach ($menusToInspect as $edge) {
            if (($edge['type'] ?? null) !== 'route') {
                continue;
            }

            if (($edge['destination_type'] ?? null) !== IvrDestinationType::IvrMenu->value) {
                continue;
            }

            if ((int) ($edge['destination_id'] ?? 0) === (int) $sourceMenu->getKey()) {
                return true;
            }

            $nextMenu = IvrMenu::query()
                ->forTenant($sourceMenu->tenant_id)
                ->whereKey($edge['destination_id'])
                ->first();

            if ($nextMenu && $this->wouldCreateLoop($sourceMenu, $nextMenu, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $notes
     * @param array<string, mixed>|null $option
     * @return array<string, mixed>
     */
    private function buildPlan(IvrMenu $menu, string $inputType, ?string $digit, string $reason, ?array $destination, array $notes = [], ?IvrOption $option = null): array
    {
        return [
            'ivr_menu' => [
                'id' => $menu->id,
                'uuid' => $menu->uuid,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'status' => $menu->status?->value ?? $menu->status,
            ],
            'resolved_at' => Carbon::now()->toISOString(),
            'input_type' => $inputType,
            'digit' => $digit,
            'reason' => $reason,
            'option' => $option ? [
                'id' => $option->id,
                'uuid' => $option->uuid,
                'digit' => $option->digit,
                'label' => $option->label,
            ] : null,
            'destination' => $destination,
            'notes' => $notes,
        ];
    }
}
