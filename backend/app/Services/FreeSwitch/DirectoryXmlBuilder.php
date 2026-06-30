<?php

namespace App\Services\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use App\Models\Extension;
use App\Services\Tenancy\TenantContext;

class DirectoryXmlBuilder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Build a FreeSWITCH directory XML payload from tenant-scoped DB data.
     *
     * WHY:
     * Laravel must become the source of truth for PBX provisioning before any
     * live directory adapter is wired in, so this builder stays contract-only
     * and refuses to guess across tenant boundaries.
     *
     * Static XML users remain the local-demo fallback; this builder only emits
     * a password when the caller explicitly opts into local-demo mode.
     */
    public function build(Extension $extension, bool $includePassword = false): ?string
    {
        $tenant = $this->tenantContext->requireTenant();
        $extension->loadMissing(['tenant', 'credential']);

        if ((string) $extension->tenant_id !== (string) $tenant->getKey()) {
            return null;
        }

        if (! $this->tenantIsProvisionable($extension->tenant) || ! $this->extensionIsProvisionable($extension)) {
            return null;
        }

        $directoryDomain = trim((string) config('freeswitch.directory_domain', ''));

        if ($directoryDomain === '') {
            return null;
        }

        $passwordParamXml = '';

        if ($includePassword) {
            if (! $this->isLocalDemoCredentialMode()) {
                return null;
            }

            $credential = $extension->credential;
            if (! $credential) {
                return null;
            }

            $password = $this->resolvePassword((string) $credential->secret_encrypted);
            if ($password === null || $password === '') {
                return null;
            }

            $passwordParamXml = sprintf(
                "                <param name=\"password\" value=\"%s\" />\n",
                $this->xmlEscape($password),
            );
        }

        $displayName = trim((string) ($extension->label ?: sprintf('Extension %s', $extension->number)));

        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<document type="freeswitch/xml">
  <section name="directory">
    <domain name="%1$s">
      <params />
      <groups>
        <group name="default">
          <users>
            <user id="%2$s">
              <params>
%3$s                <param name="vm-password" value="%2$s" />
              </params>
              <variables>
                <variable name="user_context" value="default" />
                <variable name="effective_caller_id_name" value="%4$s" />
                <variable name="effective_caller_id_number" value="%2$s" />
                <variable name="outbound_caller_id_name" value="FreeSWITCH" />
                <variable name="outbound_caller_id_number" value="0000000000" />
              </variables>
            </user>
          </users>
        </group>
      </groups>
    </domain>
  </section>
</document>
XML,
            $this->xmlEscape($directoryDomain),
            $this->xmlEscape((string) $extension->number),
            $passwordParamXml,
            $this->xmlEscape($displayName),
        );
    }

    private function tenantIsProvisionable(?object $tenant): bool
    {
        if (! $tenant) {
            return false;
        }

        $status = $tenant->status;
        if ($status instanceof TenantStatus) {
            return $status === TenantStatus::Active;
        }

        return (string) $status === TenantStatus::Active->value;
    }

    private function extensionIsProvisionable(Extension $extension): bool
    {
        $status = $extension->status;

        if ($status instanceof ExtensionStatus) {
            return $status === ExtensionStatus::Active;
        }

        return (string) $status === ExtensionStatus::Active->value;
    }

    private function resolvePassword(string $encryptedPassword): ?string
    {
        try {
            return decrypt($encryptedPassword);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Local demo mode is the only time the builder may emit a plaintext
     * password. Static XML files stay the fallback otherwise.
     */
    private function isLocalDemoCredentialMode(): bool
    {
        return config('app.env') === 'local'
            && config('freeswitch.enabled', false)
            && config('freeswitch.local_demo_credentials', false);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
