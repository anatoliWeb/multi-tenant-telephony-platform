<?php

namespace Tests\Unit\Actions;

use App\Actions\Users\CreateUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_user_with_hashed_password(): void
    {
        $action = new CreateUserAction();

        $user = $action->execute([
            'name' => 'Action User',
            'email' => 'action.user@example.com',
            'password' => 'plain-secret',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Action User', $user->name);
        $this->assertSame('action.user@example.com', $user->email);
        $this->assertNotSame('plain-secret', $user->password);
        $this->assertTrue(Hash::check('plain-secret', $user->password));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Action User',
            'email' => 'action.user@example.com',
        ]);
    }
}

