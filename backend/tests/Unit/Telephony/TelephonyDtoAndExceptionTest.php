<?php

use App\DTO\Telephony\TelephonyCallOptions;
use App\DTO\Telephony\TelephonyCallParty;
use App\DTO\Telephony\TelephonyCallResult;
use App\DTO\Telephony\TelephonyEndpointInput;
use App\DTO\Telephony\TelephonyProviderDescriptor;
use App\DTO\Telephony\TelephonyProviderHealth;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Enums\Telephony\TelephonyCapability;
use App\Enums\Telephony\TelephonyEndpointStatus;
use App\Enums\Telephony\TelephonyFailureCode;
use App\Enums\Telephony\TelephonyProviderStatus;
use App\Exceptions\Telephony\TelephonyOperationFailedException;

test('telephony provider descriptor serializes provider neutral payload', function () {
    $dto = new TelephonyProviderDescriptor(
        providerId: 'fake',
        displayName: 'Fake Telephony Provider',
        capabilities: [TelephonyCapability::CallOrigination, TelephonyCapability::CallTransfer],
        version: 'fake-1.0',
    );

    expect($dto->toArray())->toBe([
        'provider_id' => 'fake',
        'display_name' => 'Fake Telephony Provider',
        'capabilities' => ['call_origination', 'call_transfer'],
        'version' => 'fake-1.0',
    ]);
});

test('telephony provider health serializes without secrets', function () {
    $dto = new TelephonyProviderHealth(
        providerId: 'fake',
        status: TelephonyProviderStatus::Healthy,
        latencyMs: 4,
        degradedReasons: [],
        checkedAt: '2026-06-25T12:00:00Z',
    );

    expect($dto->toArray())->toBe([
        'provider_id' => 'fake',
        'status' => 'healthy',
        'latency_ms' => 4,
        'degraded_reasons' => [],
        'checked_at' => '2026-06-25T12:00:00Z',
    ]);
});

test('telephony endpoint input and call result serialize predictable array payloads', function () {
    $input = new TelephonyEndpointInput(
        tenantId: 'tenant-a',
        endpointKey: 'agent-1001',
        address: '1001@example.test',
        displayName: 'Agent 1001',
        desiredStatus: TelephonyEndpointStatus::Active,
        correlationId: 'corr-1',
        idempotencyKey: 'idem-1',
        metadata: ['region' => 'eu'],
    );

    $call = new TelephonyCallResult(
        tenantId: 'tenant-a',
        callId: 'call-1',
        providerCallId: 'provider-call-1',
        status: TelephonyCallStatus::Ringing,
        direction: TelephonyCallDirection::Outbound,
        from: new TelephonyCallParty(identifier: 'user-a', displayName: 'User A'),
        to: new TelephonyCallParty(identifier: 'user-b', displayName: 'User B'),
        correlationId: 'corr-2',
        idempotencyKey: 'idem-2',
        metadata: ['source' => 'test'],
        createdAt: '2026-06-25T12:05:00Z',
    );

    expect($input->toArray()['tenant_id'])->toBe('tenant-a');
    expect($input->toArray()['desired_status'])->toBe('active');
    expect($call->toArray()['status'])->toBe('ringing');
    expect($call->toArray()['direction'])->toBe('outbound');
    expect($call->toArray()['from']['identifier'])->toBe('user-a');
});

test('telephony call options preserve tenant scoped idempotency metadata', function () {
    $options = new TelephonyCallOptions(
        tenantId: 'tenant-a',
        direction: TelephonyCallDirection::Outbound,
        correlationId: 'corr-3',
        idempotencyKey: 'idem-3',
        metadata: ['flow' => 'local-dev'],
    );

    expect($options->toArray())->toBe([
        'tenant_id' => 'tenant-a',
        'direction' => 'outbound',
        'correlation_id' => 'corr-3',
        'idempotency_key' => 'idem-3',
        'metadata' => ['flow' => 'local-dev'],
    ]);
});

test('telephony exceptions expose safe public payloads without provider codes', function () {
    $exception = new TelephonyOperationFailedException(
        message: 'Telephony operation failed.',
        providerCode: 'SECRET_PROVIDER_CODE',
        correlationId: 'corr-4',
    );

    expect($exception->failureCode())->toBe(TelephonyFailureCode::OperationFailed);
    expect($exception->providerCode())->toBe('SECRET_PROVIDER_CODE');
    expect($exception->toSafeArray())->toBe([
        'message' => 'Telephony operation failed.',
        'failure_code' => 'operation_failed',
        'correlation_id' => 'corr-4',
    ]);
    expect(json_encode($exception->toSafeArray(), JSON_THROW_ON_ERROR))->not->toContain('SECRET_PROVIDER_CODE');
});
