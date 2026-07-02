@extends('layouts.app')

@section('title', '會員管理')

@push('css')
    @include('components.datatable.css')
@endpush

@section('content')
    <div class="container">
        <h1>會員清單</h1>
        {!! $dataTable->table() !!}
    </div>
@endsection

@push('js')
    @include('components.datatable.js', ['exportButton' => true])
    {!! $dataTable->scripts() !!}
@endpush
