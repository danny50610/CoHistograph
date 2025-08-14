<!DOCTYPE html>
<html lang="{{ strtolower(App::getLocale()) == 'zh_tw' ? 'zh-Hant-TW' : 'en' }}">
<head>
    {{-- Required meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $title = '';
        if(View::hasSection('title')) {
            $title = View::yieldContent('title') . ' - ';
        }
        $title .= config('illya.app.display-name');
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
    <meta name="theme-color" content="#343a40">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=wAXRdRBYrP">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=wAXRdRBYrP">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=wAXRdRBYrP">
    <link rel="manifest" href="/site.webmanifest?v=wAXRdRBYrP">
    <link rel="mask-icon" href="/safari-pinned-tab.svg?v=wAXRdRBYrP" color="#343a40">
    <link rel="shortcut icon" href="/favicon.ico?v=wAXRdRBYrP">
    <meta name="msapplication-TileColor" content="#343a40">
    @yield('meta')
    @yield('structured-data')

    {{-- @section('title', 'value') 會先做 e ，所以用 {!! !!} 避免二次 e --}}
    {{-- 避免使用 @section @endsection --}}
    <title>{!! $title !!}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css"
          integrity="sha512-P5MgMn1jBN01asBgU0z60Qk4QxiXo86+wlFahKrsQf37c9cro517WzVSPPV1tDKzhku2iJ2FVgL67wG03SGnNA==" crossorigin="anonymous"/>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
          integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css'])

    @stack('css')
    @stack('head-js')

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

<div id="illya-top-div" style="min-height: calc(100vh - 56px - 54px - 2rem);" class="mt-3 mb-3">
    @include('components.page-alert')
    @yield('content')
</div>

@include('components.footer')

{{-- JavaScript --}}
{{-- jQuery first, then Popper.js, then Bootstrap JS --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"
        integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"
        integrity="sha512-ubuT8Z88WxezgSqf3RLuNi5lmjstiJcyezx34yIU2gAHonIi27Na7atqzUZCOoY4CExaoFumzOsFQ2Ch+I/HCw==" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.min.js"
        integrity="sha512-XKa9Hemdy1Ui3KSGgJdgMyYlUg1gM+QhL6cnlyTe2qzMCYm4nAZ1PsVerQzTTXzonUR+dmswHqgJPuwCq1MaAg==" crossorigin="anonymous"></script>

@yield('js')
</body>
</html>
