<?php

namespace App\Services\FreeSwitch;

use App\Enums\Extensions\ExtensionStatus;
use App\Enums\TenantStatus;
use App\Models\Extension;
use App\Services\Tenancy\TenantContext;

class DialplanXmlBuilder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Build a safe dialplan contract payload for one tenant-local extension.
     *
     * WHY:
     * Tenant isolation has to be proven before any DB-backed FreeSWITCH
     * routing is connected, so the builder only emits a route when the active
     * tenant can actually own the destination.
     */
    public function buildForDestination(string $destinationNumber): string
    {
        $tenant = $this->tenantContext->requireTenant();
        $normalizedDestination = trim($destinationNumber);

        if (! $this->tenantIsProvisionable($tenant) || $normalizedDestination === '') {
            return $this->buildNoRouteXml();
        }

        $extension = Extension::query()
            ->with(['tenant', 'credential'])
            ->forTenant($tenant)
            ->where('number', $normalizedDestination)
            ->where('status', ExtensionStatus::Active->value)
            ->first();

        if (! $extension) {
            return $this->buildNoRouteXml();
        }

        $directoryDomain = trim((string) config('freeswitch.directory_domain', ''));
        if ($directoryDomain === '') {
            return $this->buildNoRouteXml();
        }

        return sprintf(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<document type="freeswitch/xml">
  <section name="dialplan">
    <context name="default">
      <extension name="extension-%1$s">
        <condition field="destination_number" expression="^%1$s$">
          <action application="bridge" data="user/%1$s@%2$s" />
        </condition>
      </extension>
    </context>
  </section>
</document>
XML,
            $this->xmlEscape((string) $extension->number),
            $this->xmlEscape($directoryDomain),
        );
    }

    private function tenantIsProvisionable(object $tenant): bool
    {
        $status = $tenant->status;

        if ($status instanceof TenantStatus) {
            return $status === TenantStatus::Active;
        }

        return (string) $status === TenantStatus::Active->value;
    }

    private function buildNoRouteXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<document type="freeswitch/xml">
  <section name="dialplan">
    <context name="default">
      <extension name="no-route">
        <condition field="destination_number" expression="^$" break="never">
          <action application="log" data="FreeSWITCH provisioning harness: no route available." />
        </condition>
      </extension>
    </context>
  </section>
</document>
XML;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
