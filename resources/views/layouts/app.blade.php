<!DOCTYPE html>
<html lang="{{ strtolower(App::getLocale()) == 'zh_tw' ? 'zh-Hant-TW' : 'en' }}">
<head>
    {{-- Required meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Fork 可新增 resources/views/custom/head.blade.php（上游不提供此檔，避免同步衝突） --}}
    @includeIf('custom.head')

    @php
        $title = '';
        if(View::hasSection('title')) {
            $title = View::yieldContent('title') . ' - ';
        }
        $title .= config('cohistograph.app.display-name');
    @endphp

    {{-- section 似乎會先做 e ，所以用 {!! !!} 避免二次 e --}}
    @if(View::hasSection('description'))
        <meta name="description" content="@yield('description')">
        <meta property="og:description" content="@yield('description')">
    @endif

    <meta property="og:title" content="{!! $title !!}">
    @if(View::hasSection('og:image'))
        <meta property="og:image" content="@yield('og:image')">
    @endif
    <meta property="og:url" content="{{ Request::url() }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ Request::url() }}">
    @yield('meta')
    @yield('structured-data')

    {{-- @section('title', 'value') 會先做 e ，所以用 {!! !!} 避免二次 e --}}
    {{-- 避免使用 @section @endsection --}}
    <title>{!! $title !!}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.7/css/bootstrap.min.css"
          integrity="sha512-fw7f+TcMjTb7bpbLJZlP8g2Y4XcCyFZW8uy8HsRZsH/SwbMw0plKHFHr99DN3l04VsYNwvzicUX/6qurvIxbxw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
          integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('css')
    @stack('head-js')

    <x-inertia::head />

    @if(isset($disableGoogleAnalytics) ? !$disableGoogleAnalytics : true)
        @if(config('app.env') == 'production' && !empty(config('services.google_analytics.id')))
            {{-- @formatter:off --}}
            {{-- Global Site Tag (gtag.js) - Google Analytics --}}
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', '{{ config('services.google_analytics.id') }}');
            </script>
            {{-- @formatter:on --}}
        @else
            <script>
                function gtag(){console.log(arguments);}
            </script>
        @endif
    @endif
</head>
<body>
@include('components.navbar.navbar')

{{-- Fork 可新增 resources/views/custom/content-top.blade.php（上游不提供此檔，避免同步衝突） --}}
@includeIf('custom.content-top')

<div style="min-height: calc(100vh - 56px - 54px - 2rem);" class="mt-3 mb-3">
    @include('components.page-alert')
    @yield('content')
    @if (isset($page['component']))
        <x-inertia::app />
    @endif
</div>

{{-- Fork 可新增 resources/views/custom/content-bottom.blade.php（上游不提供此檔，避免同步衝突） --}}
@includeIf('custom.content-bottom')

@include('components.footer')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.7/js/bootstrap.bundle.min.js"
        integrity="sha512-Tc0i+vRogmX4NN7tuLbQfBxa8JkfUSAxSFVzmU31nVdHyiHElPPy2cWfFacmCJKw0VqovrzKhdd2TSTMdAxp2g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@stack('js')
</body>
</html>
