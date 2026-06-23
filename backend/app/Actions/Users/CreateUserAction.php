<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Create base user record with hashed password.
 *
 * WHY:
 * This action encapsulates a single write operation and keeps
 * user creation persistence logic reusable and isolated.
 */
class CreateUserAction
{
    /**
     * @param array{name:string,email:string,password:string} $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}

