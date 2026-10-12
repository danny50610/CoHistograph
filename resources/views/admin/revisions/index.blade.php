@extends('layouts.app')

@section('title', '修訂審核')

@section('content')
    <div class="container">
        <h1>修訂審核</h1>

        <div class="btn-group mb-3 flex-wrap" role="group" aria-label="依狀態篩選">
            <a href="{{ route('admin.revisions.index') }}"
               class="btn btn-outline-primary {{ $currentStatus === null ? 'active' : '' }}">全部</a>
            @foreach ($statusFilters as $status)
                <a href="{{ route('admin.revisions.index', ['status' => $status->value]) }}"
                   class="btn btn-outline-primary {{ $currentStatus === $status ? 'active' : '' }}">{{ $status->label() }}</a>
            @endforeach
        </div>

        @forelse ($revisions as $revision)
            @include('revisions.partials.list-card', [
                'revision' => $revision,
                'mode' => 'admin-list',
            ])
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    @if ($currentStatus)
                        目前沒有「{{ $currentStatus->label() }}」狀態的修訂
                    @else
                        目前還沒有任何修訂
                    @endif
                </div>
            </div>
        @endforelse

        <div class="mt-3">
            {{ $revisions->links() }}
        </div>
    </div>
@endsection
