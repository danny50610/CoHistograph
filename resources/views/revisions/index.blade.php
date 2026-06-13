@extends('layouts.app')

@section('title', '我的修訂')

@section('content')
    <div class="container">
        <h1>我的修訂</h1>
        <div class="mb-3">
            <a href="{{ route('revisions.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> 新增修訂
            </a>
        </div>

        @forelse ($revisions as $revision)
            @include('revisions.partials.list-card', [
                'revision' => $revision,
                'mode' => 'user-list',
            ])
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    目前還沒有任何修訂
                </div>
            </div>
        @endforelse

        <div class="mt-3">
            {{ $revisions->links() }}
        </div>
    </div>
@endsection
