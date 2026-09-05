<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\PhoneVerification\PhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PhoneVerificationController
{
    public function __construct(private readonly PhoneVerificationService $service) {}

    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user()?->hasVerifiedPhone() === true) {
            return redirect()->route('home');
        }

        return view('auth.verify-phone');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedPhone()) {
            return redirect()->route('home');
        }

        $code = $this->service->issue($user);

        $status = __('phone_verification.sent');

        // Hors production seulement : sans prestataire SMS réel, le parcours
        // serait impossible à dérouler en développement (Q-15).
        if ($code !== null && ! app()->environment('production')) {
            $status .= ' '.__('phone_verification.dev_code', ['code' => $code]);
        }

        return back()->with('status', $status);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $outcome = $this->service->verify($user, $validated['code']);

        if ($outcome->isSuccess()) {
            return redirect()->route('home')->with('status', __($outcome->translationKey()));
        }

        return back()->withErrors(['code' => __($outcome->translationKey())]);
    }
}
