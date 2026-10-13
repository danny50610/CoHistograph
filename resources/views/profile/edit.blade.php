@extends('layouts.app')

@section('title', '個人資料')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>個人資料</h1>

                <div class="card mb-3">
                    <div class="card-header">基本資料</div>
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('profile.update') }}">
                            @method('patch')
                            @csrf

                            <x-forms.input
                                id="email"
                                label="信箱"
                                type="email"
                                :value="$user->email"
                                readonly
                                disabled
                                helpText="信箱作為帳號使用，故無法修改"
                            />

                            <x-forms.input
                                id="name"
                                label="名稱"
                                :value="$user->name"
                                required
                            />

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">更新資料</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">修改密碼</div>
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('profile.password') }}">
                            @method('put')
                            @csrf

                            <x-forms.input
                                id="current_password"
                                label="目前密碼"
                                type="password"
                                autocomplete="current-password"
                                required
                            />

                            <x-forms.input
                                id="password"
                                label="新密碼"
                                type="password"
                                autocomplete="new-password"
                                helpText="密碼長度至少需要在 8 個字以上"
                                minlength="8"
                                required
                            />

                            <x-forms.input
                                id="password_confirmation"
                                label="確認新密碼"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            />

                            <div class="mb-3 row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">更新密碼</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
