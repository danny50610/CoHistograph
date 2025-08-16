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

                            <div class="mb-3 row">
                                <label for="name" class="col-md-2 col-form-label">名稱</label>

                                <div class="col-md-10">
                                    <input id="name" type="text"
                                           class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}"
                                           name="name"
                                           value="{{ old('name') }}" required
                                           autofocus>

                                    @if ($errors->has('name'))
                                        <div class="invalid-feedback">
                                            <strong>{{ $errors->first('name') }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="email" class="col-md-2 col-form-label">信箱</label>

                                <div class="col-md-10">
                                    <input id="email" type="email"
                                           class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                                           name="email" value="{{ old('email') }}"
                                           required>

                                    @if ($errors->has('email'))
                                        <div class="invalid-feedback">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="password" class="col-md-2 col-form-label">密碼</label>

                                <div class="col-md-10">
                                    <input id="password" type="password"
                                           class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                           aria-describedby="passwordHelpInline"
                                           autocomplete="new-password"
                                           minlength="8"
                                           name="password" required>

                                    <small id="passwordHelpInline" class="text-muted">
                                        密碼長度至少需要在 8 個字以上
                                    </small>

                                    @if ($errors->has('password'))
                                        <div class="invalid-feedback">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="password-confirm" class="col-md-2 col-form-label">確認密碼</label>

                                <div class="col-md-10">
                                    <input id="password-confirm" type="password" class="form-control"
                                           name="password_confirmation"
                                           autocomplete="new-password"
                                           minlength="8"
                                           required>
                                </div>
                            </div>

                            {{-- <div class="form-group row">
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

@section('js')
    <script>
        function handleSubmit(form) {
            form.registerButton.disabled = true;
            form.registerButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 註冊';
            return true;
        }
    </script>
@endsection
