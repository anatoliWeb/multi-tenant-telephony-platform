<?php

namespace App\Services\Extensions;

use App\Models\Extension;
use App\Models\ExtensionCredential;
use App\Models\User;
use Illuminate\Support\Str;

class ExtensionCredentialService
{
    /**
     * @return array{credential: ExtensionCredential, plain_secret: string}
     */
    public function rotate(Extension $extension, User $actor, string $username): array
    {
        $plainSecret = Str::random(24);
        $credential = $extension->credential ?? new ExtensionCredential([
            'tenant_id' => $extension->tenant_id,
            'extension_id' => $extension->getKey(),
        ]);

        $credential->fill([
            'tenant_id' => $extension->tenant_id,
            'extension_id' => $extension->getKey(),
            'username' => $username,
            'secret_encrypted' => encrypt($plainSecret),
            'secret_hint' => substr($plainSecret, -4),
            'version' => ((int) $credential->version) + 1,
            'rotated_by' => $actor->getKey(),
            'rotated_at' => now(),
        ]);
        $credential->save();

        return [
            'credential' => $credential->fresh(),
            'plain_secret' => $plainSecret,
        ];
    }
}
