@extends('layouts.app')

@section('title', '重設密碼')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>重設密碼</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">

                            <x-forms.input
                                id="email"
                                label="信箱"
                                type="email"
                                :value="$email ?? ''"
                                required
                                readonly
                            />

                            <x-forms.input
                                id="password"
                                label="密碼"
                                type="password"
                                autocomplete="new-password"
                                helpText="密碼長度至少需要在 8 個字以上"
                                minlength="8"
                                required
                                autofocus
                            />

                            <x-forms.input
                                id="password_confirmation"
                                label="確認密碼"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            />

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">
                                        重設密碼
                                    </button>
                                    <a class="btn btn-link" href="{{ route('login') }}">
                                        返回登入頁
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
