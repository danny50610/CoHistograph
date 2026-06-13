@extends('layouts.app')

@section('title', '修訂審核')

@section('content')
    <div class="container">
        <h1>修訂審核</h1>

        @forelse ($revisions as $revision)
            @include('revisions.partials.list-card', [
                'revision' => $revision,
                'mode' => 'admin-list',
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
