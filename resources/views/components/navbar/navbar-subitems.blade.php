@foreach($items as $item)
    @if(Arr::get($item->data(), 'method', '') === 'POST')
        <form method="post" action="{{ $item->url() }}">
            @csrf
            <button @lm_attrs($item) type="submit" style="cursor: pointer" class="dropdown-item" @lm_endattrs>
                {{ $item->title }}
            </button>
        </form>
    @else
        @if($item->link)
            <a @lm_attrs($item) class="dropdown-item" href="{{ $item->url() }}" @lm_endattrs>
                {{ $item->title }}
                @if(Arr::get($item->attr(), 'target', false))
                    <i class="fa-solid fa-external-link-alt" aria-hidden="true"></i>
                @endif
            </a>
        @else
            {{ $item->title }}
        @endif
    @endif

    @if($item->divider)
        <div class="dropdown-divider"></div>
    @endif
@endforeach
