@extends('layouts.app')

@section('title', '常見問題管理')

@section('content')
    <div class="container">
        <h1>常見問題管理</h1>
        <a href="{{ route('admin.faq-items.create') }}" class="btn btn-primary">
            <i class="far fa-plus-square" aria-hidden="true"></i> 新增問題
        </a>
        <div class="table-responsive mt-1">
            <table class="table table-bordered table-hover">
                <thead>
                <tr>
                    <th style="width: 5rem;">順序</th>
                    <th>問題</th>
                    <th style="width: 6rem;">狀態</th>
                    <th style="width: 14rem;">操作</th>
                </tr>
                </thead>
                <tbody>
                @forelse($faqItems as $faqItem)
                    <tr @class(['table-secondary' => $faqItem->is_hidden])>
                        <td class="align-middle">{{ $loop->iteration }}</td>
                        <td class="align-middle">{{ $faqItem->question }}</td>
                        <td class="align-middle">
                            @if($faqItem->is_hidden)
                                <span class="badge text-bg-secondary">隱藏</span>
                            @else
                                <span class="badge text-bg-success">顯示</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('admin.faq-items.edit', $faqItem) }}" class="btn btn-primary">
                                <i class="far fa-edit" aria-hidden="true"></i> 編輯
                            </a>
                            {{ html()->form('DELETE', route('admin.faq-items.destroy', $faqItem))->style('display: inline')->attribute('onSubmit', "return confirm('確定要刪除此問題嗎？');")->open() }}
                            <button type="submit" class="btn btn-danger">
                                <i class="far fa-trash-alt" aria-hidden="true"></i> 刪除
                            </button>
                            {{ html()->form()->close() }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-4">
                            目前還沒有任何常見問題
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
