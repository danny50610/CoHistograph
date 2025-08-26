@extends('layouts.app')

@section('title', "會員資料")

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <a href="{{ route('user.index') }}" class="btn btn-secondary mb-2">
                    <i class="fa fa-arrow-left" aria-hidden="true"></i> 會員清單
                </a>
                <h1>會員資料</h1>
                <div class="card mb-2">
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-3">名稱</dt>
                            <dd class="col-sm-9">{{ $user->name }}</dd>

                            <dt class="col-sm-3">Email</dt>
                            <dd class="col-sm-9">{{ $user->email }}</dd>

                            <dt class="col-sm-3">角色</dt>
                            <dd class="col-sm-9">
                                @forelse($user->roles()->orderby('id')->get() as $role)
                                    <span class="badge bg-primary">{{ $role->display_name }}</span><br/>
                                @empty
                                    無<br/>
                                @endforelse
                            </dd>
                        </dl>
                        <a href="{{ route('user.edit', $user) }}" class="btn btn-primary">編輯資料</a>
                        {{ html()->form('DELETE', route('user.destroy', [$user]))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此會員嗎？');")->open() }}
                        <button type="submit" class="btn btn-danger">刪除會員</button>
                        {{ html()->form()->close() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
