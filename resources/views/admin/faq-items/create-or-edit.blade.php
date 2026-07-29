@extends('layouts.app')

@php
    $isEditMode = isset($faqItem);
    $methodText = $isEditMode ? '編輯' : '新增';
@endphp

@section('title', $methodText . '常見問題')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>{{ $methodText }}常見問題</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST"
                              action="{{ $isEditMode ? route('admin.faq-items.update', $faqItem) : route('admin.faq-items.store') }}">
                            @if($isEditMode)
                                @method('patch')
                            @endif
                            @csrf

                            <x-forms.input
                                id="question"
                                label="問題"
                                :value="$faqItem->question ?? ''"
                                placeholder="例如：如何註冊帳號？"
                                required
                            />

                            <x-forms.textarea
                                id="answer"
                                label="回答"
                                :value="$faqItem->answer ?? ''"
                                placeholder="請輸入回答內容"
                                :rows="8"
                                required
                            />

                            <x-forms.input
                                id="sort_order"
                                label="排序"
                                type="number"
                                :value="(string) ($faqItem->sort_order ?? $nextSortOrder ?? 0)"
                                help-text="數字越小越靠前"
                                required
                            />

                            <div class="form-group row">
                                <div class="col-md-10 ml-auto">
                                    <button type="submit" class="btn btn-primary">
                                        {{ $isEditMode ? '更新' : '新增' }}
                                    </button>
                                    <a href="{{ route('admin.faq-items.index') }}" class="btn btn-secondary">返回列表</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
