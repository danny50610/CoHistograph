@inject('menu', 'App\Services\MenuService')

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">{{ config('cohistograph.app.display-name') }}</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse"
                data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Item 會由 LaravelMenu 生成 --}}
        <div class="collapse navbar-collapse" id="navbarResponsive">
            {{-- 左側選單 --}}
            <ul class="navbar-nav">
                @include('components.navbar.navbar-items', ['items' => Menu::get('left')->roots()])
            </ul>
            {{-- 右側選單 --}}
            <ul class="navbar-nav ms-auto">
                @include('components.navbar.navbar-items', ['items' => Menu::get('right')->roots()])
            </ul>
        </div>
    </div>
</nav>
