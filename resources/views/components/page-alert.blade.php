@php
    $alertType = null;
    if (session('global')) {
        $alertType = 'alert-success';
        $alertInfo = '<i class="fas fa-info-circle"></i>';
        $alertMessage = session('global');
    } elseif (session('warning')) {
        $alertType = 'alert-warning';
        $alertInfo = '<i class="fas fa-exclamation-circle"></i>';
        $alertMessage = session('warning');
    }
@endphp

@if(!is_null($alertType))
    <div class="alert {{ $alertType }} alert-dismissible fade show" role="alert"
         style="margin-bottom: 0; top: -1rem; padding: 12px 0; border-radius: 0">
        <div class="container" style="position: relative;">
            {!! $alertInfo !!} {{ $alertMessage }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                style="padding: 12px 20px; top: -12px; height: 24px;">
            </button>
        </div>
    </div>
@endif
