<?php

namespace App\Services\CallControl;

use App\Models\Extension;
use App\Services\Tenancy\TenantContext;

class SipProfileService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Build a tenant-safe SIP profile for the selected extension.
     *
     * The profile intentionally omits live SIP secrets in this stage so the
     * browser can prepare UI and media plumbing without persisting credentials
     * in long-lived state before the FreeSWITCH provisioning slice exists.
     *
     * @return array<string, mixed>
     */
    public function build(Extension $extension): array
    {
        $credentialUsername = (string) ($extension->credential?->username ?? $extension->credential_username ?? $extension->number);
        $domain = $this->resolveDomain();

        return [
            'extension_id' => $extension->getKey(),
            'extension_number' => (string) $extension->number,
            'display_name' => $extension->label ?: sprintf('Extension %s', $extension->number),
            'sip_uri' => sprintf('sip:%s@%s', $credentialUsername, $domain),
            'authorization_username' => $credentialUsername,
            'websocket_url' => sprintf('%s://%s:%d', $this->resolveWebSocketScheme(), $domain, (int) config('freeswitch.ports.wss', 7443)),
            'domain' => $domain,
            'provider' => 'freeswitch',
            'expires_seconds' => 300,
            'capabilities' => [
                'outbound_call' => true,
                'inbound_call' => false,
                'hold' => false,
                'mute' => true,
            ],
            'registration' => [
                'enabled' => false,
                'state' => 'disabled',
                'reason' => 'SIP registration stays disabled until a safe tenant directory provisioning slice exists.',
            ],
            'tenant_id' => (string) $this->tenantContext->requireTenant()->getKey(),
        ];
    }

    private function resolveDomain(): string
    {
        $appUrl = (string) config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function resolveWebSocketScheme(): string
    {
        return config('freeswitch.enabled', false) ? 'wss' : 'ws';
    }
}
