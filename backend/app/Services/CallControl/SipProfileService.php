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
     * Local demo credentials are only returned when the explicit local gate is
     * open. In every other environment the profile stays metadata-only so the
     * browser can prepare UI and media plumbing without persisting secrets in
     * long-lived state before the FreeSWITCH provisioning slice exists.
     *
     * @return array<string, mixed>
     */
    public function build(Extension $extension): array
    {
        $credentialUsername = (string) ($extension->credential?->username ?? $extension->credential_username ?? $extension->number);
        $domain = $this->resolveDomain();
        $localDemoMode = $this->isLocalDemoMode();
        $password = $this->resolveDemoPassword($localDemoMode);
        $credentialsAvailable = $password !== null;
        $registrationEnabled = $credentialsAvailable;
        $registrationReason = $credentialsAvailable
            ? 'Local demo SIP credentials are enabled for this development environment.'
            : 'SIP credentials are not enabled for this environment.';

        $payload = [
            'extension_id' => $extension->getKey(),
            'extension_number' => (string) $extension->number,
            'display_name' => $extension->label ?: sprintf('Extension %s', $extension->number),
            'sip_uri' => sprintf('sip:%s@%s', $credentialUsername, $domain),
            'authorization_username' => $credentialUsername,
            'websocket_url' => $this->resolveWebSocketUrl($domain),
            'domain' => $domain,
            'provider' => 'freeswitch',
            'expires_seconds' => 300,
            'credentials_available' => $credentialsAvailable,
            'registration_enabled' => $registrationEnabled,
            'local_demo_mode' => $localDemoMode,
            'capabilities' => [
                'outbound_call' => true,
                'inbound_call' => false,
                'hold' => false,
                'mute' => true,
            ],
            'registration' => [
                'enabled' => $registrationEnabled,
                'state' => $registrationEnabled ? 'available' : 'disabled',
                'reason' => $registrationReason,
            ],
            'tenant_id' => (string) $this->tenantContext->requireTenant()->getKey(),
        ];

        if ($password !== null) {
            $payload['password'] = $password;
        }

        return $payload;
    }

    private function resolveDomain(): string
    {
        $configuredDomain = trim((string) config('freeswitch.sip_domain', ''));

        if ($configuredDomain !== '') {
            // The browser SIP domain must stay browser-reachable. Docker runtime
            // IPs belong to provisioning checks, not to the SIP URI shown to the
            // Angular softphone.
            return $configuredDomain;
        }

        $appUrl = (string) config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function resolveWebSocketUrl(string $domain): string
    {
        $configuredUrl = trim((string) config('freeswitch.sip_wss_url', ''));

        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        $legacyConfiguredUrl = trim((string) config('freeswitch.webrtc_wss_url', ''));

        if ($legacyConfiguredUrl !== '') {
            return $legacyConfiguredUrl;
        }

        $scheme = config('freeswitch.enabled', false) ? 'wss' : 'ws';

        return sprintf('%s://%s:%d', $scheme, $domain, (int) config('freeswitch.ports.wss', 7443));
    }

    private function isLocalDemoMode(): bool
    {
        return config('app.env') === 'local'
            && config('freeswitch.enabled', false)
            && config('freeswitch.local_demo_credentials', false);
    }

    private function resolveDemoPassword(bool $localDemoMode): ?string
    {
        if (! $localDemoMode) {
            return null;
        }

        $password = trim((string) config('freeswitch.default_sip_password', ''));

        if ($password === '') {
            return null;
        }

        return $password;
    }
}
