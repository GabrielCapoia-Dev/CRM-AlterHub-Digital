<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Acesso\User;
use App\Services\Acesso\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RequiredPasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! $user->must_change_password) {
            return redirect()->route('filament.painel.home');
        }

        return view('auth.required-password-change', [
            'user' => $user,
        ]);
    }

    public function update(
        Request $request,
        PasswordResetService $passwordResetService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! $user->must_change_password) {
            return redirect()->route('filament.painel.home');
        }

        $validated = $request->validate([
            'temporary_password' => [
                'required',
                'string',
                'max:30',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:30',
                Password::min(max(8, (int) config('crm.access.user_password_min_length', 8)))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ], [
            'temporary_password.required' => 'Informe a senha temporária recebida do administrador.',
            'temporary_password.max' => 'A senha temporária deve ter no máximo 30 caracteres.',
            'password.max' => 'A senha deve ter no máximo 30 caracteres.',
        ]);

        $passwordResetService->completeRequiredChange(
            $user,
            $validated['temporary_password'],
            $validated['password'],
        );

        // Filament's AuthenticateSession middleware remembers the password hash
        // seen before this forced change. Refresh it so this current, verified
        // session is not mistaken for a stale session on the next panel request.
        $request->session()->regenerate(true);
        $request->session()->put(
            'password_hash_'.config('auth.defaults.guard'),
            $user->getAuthPassword(),
        );

        return redirect()
            ->route('filament.painel.home')
            ->with('status', 'Senha alterada com sucesso.');
    }
}
