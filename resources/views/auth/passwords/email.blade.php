@extends('layouts.app')

@section('title', '忘記密碼')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>忘記密碼</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <x-forms.input id="email" label="信箱" type="email" required autofocus />

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">
                                        寄送重設密碼連結
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
