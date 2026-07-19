<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = HomeController::AUTHENTICATED_REDIRECT;

    protected function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'name'                 => 'required|string|max:255',
            'email'                => 'required|string|email:rfc,dns,filter|max:255|unique:users',
            'password'             => 'required|string|min:8|confirmed',
            // 'g-recaptcha-response' => 'required|captcha',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    protected function registered(Request $request, mixed $user)
    {
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return null;
    }
}
