@extends('layouts.app')

@section('title', 'API Tokens')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Tokens']
    ]" />
@endsection

@section('content')
    <x-page-header
        title="API Tokens"
        subtitle="Create and revoke personal access tokens."
    />

    @if ($errors->any())
        <x-alert type="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if(session('token'))
        <x-alert type="success">
            <strong>New token (copy now, it will not be shown again):</strong>
            <div class="c-token-box">
                <span id="token-text" class="c-token-box__value">{{ session('token') }}</span>
                <button type="button" class="c-btn c-btn--ghost" onclick="copyToken()">Copy</button>
            </div>
        </x-alert>
    @endif

    @can('tokens.create')
        <x-card>
        <form method="POST" action="{{ route('admin.tokens.store') }}" class="c-form">
            @csrf

            <section class="c-form__section">
                <div class="c-form__group">
                    <label for="name" class="c-form__label">Token Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Token name" class="c-form__input">
                </div>
            </section>

            <section class="c-form__section">
                <h2 class="c-form__title">Abilities</h2>
                <div class="c-check-grid">
                    <label class="c-check">
                        <input type="checkbox" name="abilities[]" value="read" {{ in_array('read', old('abilities', [])) ? 'checked' : '' }}>
                        <span>Read</span>
                    </label>

                    <label class="c-check">
                        <input type="checkbox" name="abilities[]" value="write" {{ in_array('write', old('abilities', [])) ? 'checked' : '' }}>
                        <span>Write</span>
                    </label>
                </div>
            </section>

            <div class="c-form__actions">
                <button type="submit" class="c-btn c-btn--primary">Create Token</button>
            </div>
        </form>
        </x-card>
    @endcan

    <x-card class="u-mt-3">
        <table class="c-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Created</th>
                <th></th>
            </tr>
            </thead>

            <tbody>
            @forelse ($tokens as $token)
                <tr>
                    <td>{{ $token->id }}</td>
                    <td>{{ $token->name }}</td>
                    <td class="c-table__muted">{{ $token->created_at->format('Y-m-d H:i') }}</td>
                    <td class="c-table__actions">
                        @can('tokens.delete')
                            <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}" onsubmit="return confirm('Delete this token?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="c-btn c-btn--danger">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div class="c-empty">No data available</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
@endsection

@push('scripts')
<script>
    function copyToken() {
        const text = document.getElementById('token-text').innerText;
        navigator.clipboard.writeText(text);
        alert('Token copied');
    }
</script>
@endpush
