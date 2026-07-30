@extends('layouts.footer-page')

@section('title', '常見問題')

@section('card-content')
    @forelse($faqItems as $faqItem)
        <div class="mb-4 @if(! $loop->last) border-bottom pb-4 @endif">
            <h2 class="h5 mb-2">{{ $faqItem->question }}</h2>
            <div class="text-body-secondary">{!! nl2br(e($faqItem->answer)) !!}</div>
        </div>
    @empty
        <p class="text-secondary mb-0">目前尚無常見問題。</p>
    @endforelse
@endsection
