<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Token management controller.
 *
 * Handles listing, creating and deleting API tokens.
 */
class TokenController extends Controller
{
    /**
     * Display tokens list.
     */
    public function index()
    {
        try {
            $tokens = auth()->user()->tokens;

            return view('admin.tokens.index', compact('tokens'));

        } catch (\Throwable $e) {
            Log::error('Failed to load tokens', [
                'error' => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    /**
     * Create new token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array'
        ]);

        /**
         * Create token with optional abilities (scopes).
         */
        $token = $request->user()->createToken(
            $request->name,
            $request->input('abilities', ['*'])
        );

        // Fallback logging for Sanctum token creation (observer should also log when available).
        activity_log('token_created', 'Created API token', [
            'token_name' => $request->name,
            'tokenable_id' => $request->user()->id,
        ]);

        return redirect()
            ->back()
            ->with('token', $token->plainTextToken);
    }

    /**
     * Delete token.
     */
    public function destroy($id)
    {
        $token = auth()->user()->tokens()->findOrFail($id);
        $tokenName = $token->name;
        $token->delete();

        // Fallback logging for Sanctum token deletion.
        activity_log('token_deleted', 'Deleted API token', [
            'token_id' => $id,
            'token_name' => $tokenName,
            'tokenable_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Token deleted');
    }
}
