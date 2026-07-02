@php
    $statusMap = [
        'draft'          => ['label' => '草稿',  'class' => 'text-bg-secondary'],
        'pending_review' => ['label' => '待審核', 'class' => 'text-bg-warning'],
        'rejected'       => ['label' => '已退回', 'class' => 'text-bg-danger'],
        'approved'       => ['label' => '已接受', 'class' => 'text-bg-success'],
    ];
    $info = $statusMap[$status->value] ?? ['label' => $status->value, 'class' => 'text-bg-secondary'];
@endphp
<span class="badge {{ $info['class'] }}">{{ $info['label'] }}</span>
