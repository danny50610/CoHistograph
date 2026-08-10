@extends('layouts.app')

@section('title', '已授權應用')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h1>已授權應用</h1>
                <p class="text-body-secondary mb-4">
                    這些應用程式（例如 Cursor、Claude）已取得你的授權，可透過 MCP 存取帳號功能。撤銷後需重新授權才能繼續使用。
                </p>

                @forelse ($apps as $app)
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <h2 class="h5 mb-1">{{ $app['name'] }}</h2>
                                    <div class="small text-body-secondary">
                                        授權於 {{ $app['authorized_at']?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                        @if ($app['token_count'] > 1)
                                            · {{ $app['token_count'] }} 組有效權杖
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('settings.authorized-apps.destroy', $app['client_id']) }}"
                                      onsubmit="return confirm('確定撤銷「{{ $app['name'] }}」的存取權限？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">撤銷存取</button>
                                </form>
                            </div>

                            <dl class="row mb-0 mt-3 small">
                                <dt class="col-sm-3 text-body-secondary">最近使用</dt>
                                <dd class="col-sm-9">
                                    {{ $app['last_used_at']?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '尚未使用' }}
                                </dd>

                                <dt class="col-sm-3 text-body-secondary">IP</dt>
                                <dd class="col-sm-9">{{ $app['last_used_ip'] ?? '—' }}</dd>

                                <dt class="col-sm-3 text-body-secondary">User-Agent</dt>
                                <dd class="col-sm-9 text-break">{{ $app['last_used_user_agent'] ?? '—' }}</dd>

                                <dt class="col-sm-3 text-body-secondary">權杖到期</dt>
                                <dd class="col-sm-9 mb-0">
                                    {{ $app['expires_at']?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                @empty
                    <div class="card">
                        <div class="card-body text-center text-secondary py-5">
                            目前沒有已授權的應用程式。
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
