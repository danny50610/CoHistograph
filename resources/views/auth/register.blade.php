@extends('layouts.app')

@section('title', '註冊')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>註冊</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('register') }}" onsubmit="return handleSubmit(this);">
                            @csrf

                            <x-forms.input id="name" label="名稱" required autofocus />

                            <x-forms.input id="email" label="信箱" type="email" required />

                            <x-forms.input
                                id="password"
                                label="密碼"
                                type="password"
                                autocomplete="new-password"
                                helpText="密碼長度至少需要在 8 個字以上"
                                minlength="8"
                                required
                            />

                            <x-forms.input
                                id="password-confirm"
                                name="password_confirmation"
                                label="確認密碼"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            />

                            {{-- <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    {!! Captcha::display() !!}
                                </div>
                            </div> --}}

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary" id="registerButton">
                                        註冊
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

@push('js')
    <script>
        function handleSubmit(form) {
            form.registerButton.disabled = true;
            form.registerButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 註冊';
            return true;
        }
    </script>
@endpush
