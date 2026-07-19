<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = HomeController::AUTHENTICATED_REDIRECT;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($this->redirectPath());
        }

        $request->user()->sendEmailVerificationNotification();

        $message = '驗證信已重新寄出';

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 202)
            : back()->with('global', $message);
    }

    protected function verified(Request $request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => '信箱驗證完成'], 200);
        }

        return redirect($this->redirectPath())
            ->with('global', '信箱驗證完成');
    }
}
