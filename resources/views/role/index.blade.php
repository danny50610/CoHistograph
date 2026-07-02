@extends('layouts.app')

@section('title', '權限管理')

@section('content')
    <div class="container">
        <h1>權限管理</h1>
        <h2>角色清單</h2>
        <a href="{{ route('role.create') }}" class="btn btn-primary">
            <i class="far fa-plus-square" aria-hidden="true"></i> 新增角色
        </a>
        <div class="table-responsive mt-1">
            <table class="table table-bordered table-hover">
                <thead>
                <tr>
                    <th>角色</th>
                    <th class="text-center">保護</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td class="align-middle">
                            {{ $role->display_name }} ({{ $role->name }})
                        </td>
                        <td class="text-center align-middle">
                            @if($role->protection)
                                <i class="fa fa-check fa-2x text-success" aria-hidden="true"></i>
                            @endif
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('role.edit', $role) }}" class="btn btn-primary">
                                <i class="far fa-edit" aria-hidden="true"></i> 編輯角色
                            </a>
                            @unless($role->protection)
                                {{ html()->form('DELETE', route('role.destroy', [$role]))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此角色嗎？');")->open() }}
                                <button type="submit" class="btn btn-danger">
                                    <i class="far fa-trash-alt" aria-hidden="true"></i> 刪除角色
                                </button>
                                {{ html()->form()->close() }}
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <h2>權限清單</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                <tr>
                    <th>權限節點</th>
                    @foreach($roles as $role)
                        <th class="text-center">{{ $role->display_name }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($permissions as $permission)
                    <tr>
                        <td>
                            {{ $permission->name }}<br/>
                            <small>
                                <i class="fa fa-angle-double-right"
                                   aria-hidden="true"></i> {{ $permission->description }}
                            </small>
                        </td>
                        @foreach($roles as $role)
                            <td class="text-center align-middle">
                                @if($permission->roles->contains($role))
                                    <i class="fa fa-check fa-2x text-success" aria-hidden="true"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
