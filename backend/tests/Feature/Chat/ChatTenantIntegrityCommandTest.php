<?php

namespace Tests\Feature\Chat;

use App\Enums\TenantStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTenantIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_tenant_integrity_command_passes_on_clean_schema(): void
    {
        $exitCode = Artisan::call('chat:verify-tenant-integrity', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($payload);
        $this->assertFalse($payload['has_failures']);
        $this->assertSame(0, $payload['mismatches']['messages_vs_conversations']);
        $this->assertSame('NO', $payload['tables']['conversations']['tenant_id_nullable']);
        $this->assertSame('NO', $payload['tables']['messages']['tenant_id_nullable']);
    }

    public function test_chat_tenant_integrity_command_detects_message_tenant_drift(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $owner = User::factory()->create();

        $conversation = Conversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'type' => 'external',
            'visibility' => 'private',
            'title' => 'Integrity Conversation',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'source' => 'api',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
        ]);

        Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $owner->id,
            'sender_type' => 'user',
            'type' => 'text',
            'body' => 'Integrity message',
            'status' => 'sent',
        ]);

        DB::table('messages')->update(['tenant_id' => $tenantB->id]);

        $exitCode = Artisan::call('chat:verify-tenant-integrity', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertTrue($payload['has_failures']);
        $this->assertSame(1, $payload['mismatches']['messages_vs_conversations']);
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => strtoupper($slug),
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => now(),
        ]);
    }
}
