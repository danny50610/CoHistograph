@extends('layouts.app')

@section('title', '信箱驗證')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>信箱驗證</h1>
                <div class="card">
                    <div class="card-body">
                        <p>我們已寄送驗證信至您的信箱，請點擊信中的驗證連結完成驗證。</p>
                        <p>若未收到驗證信，可點擊下方按鈕重新寄送。</p>

                        <form role="form" method="POST" action="{{ route('verification.resend') }}">
                            @csrf

                            <div class="mb-3 row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        重新寄送驗證信
                                    </button>
                                    <a class="btn btn-link" href="{{ route('overview') }}">
                                        返回首頁
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
