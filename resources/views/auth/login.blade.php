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

                            <x-forms.input id="email" label="信箱" type="email" required autofocus />

                            <x-forms.input id="password" label="密碼" type="password" required />

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
