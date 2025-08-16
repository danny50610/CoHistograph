@extends('layouts.app')

@section('title', '登入')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>登入</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="mb-3 row">
                                <label for="email" class="col-md-2 col-form-label">信箱</label>

                                <div class="col-md-10">
                                    <input id="email" type="email"
                                           class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                                           name="email"
                                           value="{{ old('email') }}" required autofocus>

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
                                           class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}"
                                           name="password" required>

                                    @if ($errors->has('password'))
                                        <div class="invalid-feedback">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" name="remember" id="remember">
                                        <label class="custom-control-label" for="remember">記住我</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">
                                        登入
                                    </button>

                                    <a class="btn btn-link" href="{{ route('register') }}">
                                        註冊
                                    </a>
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        忘記密碼
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
