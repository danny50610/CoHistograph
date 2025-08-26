@extends('layouts.app')

@section('title', "編輯會員資料")

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>編輯會員資料</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('user.update', $user) }}">
                            @method('patch')
                            @csrf

                            <div class="mb-3 row">
                                <label for="email" class="col-md-2 col-form-label">信箱</label>

                                <div class="col-md-10">
                                    <input id="email" type="email" class="form-control" value="{{ $user->email }}"
                                           disabled readonly>
                                    <small class="form-text text-muted">信箱作為帳號使用，故無法修改</small>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="name" class="col-md-2 col-form-label">名稱</label>

                                <div class="col-md-10">
                                    <input id="email" type="email" class="form-control" value="{{ $user->name }}">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label class="col-md-2 col-form-label">角色</label>
                                <div class="col-md-10" style="padding-top: calc(.5rem - 1px * 2);">
                                    @foreach($roles as $role)
                                        @if($user->id == Auth::user()->id && $role->name == 'Admin')
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input"
                                                       name="role[]" value="{{ $role->id }}"
                                                       id="role{{ $role->id }}"
                                                       @if($user->hasRole($role->name)) checked disabled @endif
                                                >
                                                <label class="custom-control-label" for="role{{ $role->id }}">
                                                    {{ $role->display_name }} ({{ $role->name }})
                                                </label>
                                                <br/>
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                                    禁止解除自己的管理員職務
                                                </small>
                                            </div>
                                        @else
                                            <div class="custom-control custom-checkbox mb-1">
                                                <input type="checkbox" class="custom-control-input"
                                                       name="role[]" value="{{ $role->id }}"
                                                       id="role{{ $role->id }}"
                                                       @if($user->hasRole($role->name)) checked @endif
                                                >
                                                <label class="custom-control-label" for="role{{ $role->id }}">
                                                    {{ $role->display_name }} ({{ $role->name }})
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    @if($errors->has('role'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('role') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <div class="col-md-10 ml-auto">
                                    <button type="submit" class="btn btn-primary"> 更新</button>
                                    <a href="{{ route('user.show', $user) }}" class="btn btn-secondary">返回</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
