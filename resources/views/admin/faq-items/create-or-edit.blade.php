@extends('layouts.app')

@php
    $isEditMode = isset($faqItem);
    $methodText = $isEditMode ? '編輯' : '新增';
    $selectedPlaceAfterId = old('place_after_id', $placeAfterId);
    $isHidden = (bool) old('is_hidden', $isEditMode ? $faqItem->is_hidden : false);
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

                            <div class="row mb-3">
                                <label for="place_after_id" class="col-md-2 col-form-label">位置</label>
                                <div class="col-md-10">
                                    <select id="place_after_id" name="place_after_id"
                                            class="form-select @if ($errors->has('place_after_id')) is-invalid @endif">
                                        <option value="" @selected($selectedPlaceAfterId === null || $selectedPlaceAfterId === '')>
                                            最上方
                                        </option>
                                        @foreach($placementOptions as $option)
                                            <option value="{{ $option['value'] }}"
                                                @selected((string) $selectedPlaceAfterId === (string) $option['value'])>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">選擇要放在哪個問題的下方</div>
                                    @if ($errors->has('place_after_id'))
                                        <div class="invalid-feedback d-block">
                                            @foreach ($errors->get('place_after_id') as $message)
                                                {{ $message }}
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-md-2 col-form-label">顯示</label>
                                <div class="col-md-10" style="padding-top: calc(.5rem - 1px * 2);">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input"
                                               name="is_hidden" value="1" id="is_hidden"
                                               @checked($isHidden)>
                                        <label class="custom-control-label" for="is_hidden">隱藏此問題（前台不顯示）</label>
                                    </div>
                                </div>
                            </div>

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
