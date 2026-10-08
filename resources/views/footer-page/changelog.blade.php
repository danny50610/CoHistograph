@extends('layouts.footer-page')

@section('title', '更新紀錄')

@section('card-content')
    @forelse($releases as $release)
        <article class="mb-4 @if(! $loop->last) border-bottom pb-4 @endif">
            <h1>{{ $release->version }}</h1>
            <p class="text-body-secondary">
                <time datetime="{{ $release->date->toDateString() }}">{{ $release->date->toDateString() }}</time>
                <span class="mx-1" aria-hidden="true">·</span>
                <a href="{{ $release->githubUrl() }}" target="_blank" rel="noopener noreferrer">GitHub</a>
            </p>
            {{ $release->html }}
        </article>
    @empty
        <p class="text-secondary mb-0">目前尚無更新紀錄。</p>
    @endforelse
@endsection
