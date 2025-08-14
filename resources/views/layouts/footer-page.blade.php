@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    @php
                        $pages = [
                            ['href' => route('faq'), 'name' => '常見問題'],
                        ];
                    @endphp
                    @foreach($pages as $page)
                        <li class="nav-item">
                            <a class="nav-link @if($page['href'] === Request::url()) active @endif" href="{{ $page['href'] }}">
                                {{ $page['name'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body">
                @yield('card-content')
            </div>
        </div>
    </div>
@endsection
