<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RevisionReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:revision.review');
    }

    public function index(): View
    {
        return view('revisions.wip', [
            'pageTitle' => '修訂審核',
            'pageDescription' => '管理員修訂審核列表頁骨架已建立，後續會補上列表與分頁。',
            'backRoute' => route('index'),
            'backLabel' => '返回首頁',
            'wipActions' => [],
        ]);
    }

    public function show(string $revision): View
    {
        return view('revisions.wip', [
            'pageTitle' => '修訂審核詳情',
            'pageDescription' => '管理員修訂審核頁骨架已建立，後續會補上驗證結果、審核操作與歷程。',
            'backRoute' => route('admin.revisions.index'),
            'backLabel' => '返回修訂審核',
            'referenceId' => $revision,
            'wipActions' => ['approve', 'reject'],
        ]);
    }

    public function approve(string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }

    public function reject(Request $request, string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }
}
