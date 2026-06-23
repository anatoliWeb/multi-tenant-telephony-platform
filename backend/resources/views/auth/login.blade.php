@extends('layouts.guest')

@section('title', 'Login')

@section('content')

    <div class="auth-card">
        <div class="auth-title">Login</div>
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <input type="email" name="email" value="admin@test.com" placeholder="Email" class="auth-input">
            @error('email')
            <div class="auth-error">{{ $message }}</div>
            @enderror
            <input type="password" name="password" value="password" placeholder="Password" class="auth-input">
            @error('password')
            <div class="auth-error">{{ $message }}</div>
            @enderror

            <button class="auth-button">Login</button>
        </form>
    </div>

@endsection
