@foreach($items as $item)
    <li @lm_attrs($item) class="nav-item @if($item->hasChildren()) dropdown @endif " @lm_endattrs>
        @if($item->link)
            <a @lm_attrs($item) class="nav-link @if($item->hasChildren()) dropdown-toggle @endif @if(Arr::get($item->data(), 'text-danger', false)) text-danger @endif"
                @if($item->hasChildren()) data-bs-toggle="dropdown" @endif href="{{ $item->url() }}" @lm_endattrs>
                {{ $item->title }}
            </a>
        @else
            {{ $item->title }}
        @endif
        @if($item->hasChildren())
            <div class="dropdown-menu">
                @include('components.navbar.navbar-subitems', ['items' => $item->children()])
            </div>
        @endif
    </li>
@endforeach
