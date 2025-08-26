@extends('layouts.app')

@php
    $isEditMode = isset($role);
    $methodText = $isEditMode ? '編輯' : '新增';
@endphp

@section('title', $methodText . '角色')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>{{ $methodText }}角色</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST"
                              action="{{ $isEditMode ? route('role.update', $role) : route('role.store') }}">
                            @if($isEditMode)
                                @method('patch')
                            @endif
                            @csrf
                            <div class="form-group row">
                                <label for="name" class="col-md-2 col-form-label">英文名稱 *</label>
                                <div class="col-md-10">
                                    @if($isEditMode && $role->protection)
                                        <input id="name" type="text"
                                               class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}"
                                               name="name"
                                               value="{{ $role->name }}"
                                               placeholder="如：admin" disabled>
                                        {{ html()->hidden('name', $role->name) }}
                                    @else
                                        <input id="name" type="text"
                                               class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}"
                                               name="name"
                                               value="{{ $role->name ?? '' }}"
                                               placeholder="admin" required>
                                    @endif
                                    @if($errors->has('name'))
                                        <span class="invalid-feedback">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="display_name" class="col-md-2 col-form-label">顯示名稱 *</label>
                                <div class="col-md-10">
                                    <input id="display_name" type="text"
                                           class="form-control{{ $errors->has('display_name') ? ' is-invalid' : '' }}"
                                           value="{{ $role->display_name ?? '' }}"
                                           name="display_name"
                                           placeholder="管理員" required>
                                    @if($errors->has('display_name'))
                                        <span class="invalid-feedback">
                                        <strong>{{ $errors->first('display_name') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">權限</label>
                                <div class="col-md-10" style="padding-top: calc(.5rem - 1px * 2);">
                                    @foreach($permissions as $permission)
                                        <div class="custom-control custom-checkbox mb-1">
                                            <input type="checkbox" class="custom-control-input"
                                                   name="permissions[]" value="{{ $permission->id }}"
                                                   id="permissions{{ $permission->id }}"
                                                   @if(isset($role) && $role->permissions->contains($permission)) checked
                                                   @endif
                                                   @if(isset($role) && $role->protection) disabled @endif
                                            >
                                            <label class="custom-control-label" for="permissions{{ $permission->id }}">
                                                {{ $permission->name }}
                                            </label>
                                            <br/>
                                            <small>
                                                <i class="fa fa-angle-double-right"
                                                   aria-hidden="true"></i> {{ $permission->description }}
                                            </small>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-10 ml-auto">
                                    <button type="submit"
                                            class="btn btn-primary"> {{ $isEditMode ? '更新' : '新增' }}</button>
                                    <a href="{{ route('role.index') }}" class="btn btn-secondary">返回列表</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
