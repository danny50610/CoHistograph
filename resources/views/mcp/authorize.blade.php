@extends('layouts.app')

@section('title', '授權應用程式')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>授權應用程式</h1>
                <div class="card">
                    <div class="card-body">
                        <p class="mb-3">
                            <strong>{{ $client->name }}</strong> 請求存取你的帳號，以使用可用的 MCP 功能。
                        </p>

                        <div class="mb-3">
                            <div class="text-body-secondary small">目前登入帳號</div>
                            <div>{{ $user->email }}</div>
                        </div>

                        @if(count($scopes) > 0)
                            <div class="mb-3">
                                <div class="fw-medium mb-2">權限</div>
                                <ul class="mb-0">
                                    @foreach($scopes as $scope)
                                        <li>{{ $scope->description }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm">
                                @csrf
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                                <button type="submit" class="btn btn-primary" id="authorizeButton">
                                    <span id="authorizeText">授權</span>
                                    <span id="loadingSpinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                                <button type="submit" class="btn btn-outline-secondary">取消</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('authorizeForm');
        const button = document.getElementById('authorizeButton');
        const authorizeText = document.getElementById('authorizeText');
        const loadingSpinner = document.getElementById('loadingSpinner');

        form.addEventListener('submit', function() {
            button.disabled = true;
            authorizeText.textContent = '授權中...';
            loadingSpinner.classList.remove('d-none');

            setTimeout(function() {
                const checkRedirect = setInterval(function() {
                    if (!window.location.href.includes('/oauth/authorize') ||
                        window.location.search.includes('code=') ||
                        window.location.search.includes('error=')) {
                        clearInterval(checkRedirect);
                        window.close();
                    }
                }, 100);

                setTimeout(function() {
                    clearInterval(checkRedirect);
                    window.close();
                }, 5000);
            }, 200);
        });

        const cancelForm = document.querySelector('form[method="POST"]:has(input[name="_method"][value="DELETE"])');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function() {
                setTimeout(function() {
                    window.close();
                }, 200);
            });
        }
    });
</script>
@endpush
