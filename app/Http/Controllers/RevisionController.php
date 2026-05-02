<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevisionRequest;
use App\Services\RevisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RevisionController extends Controller
{
    public function __construct(private RevisionService $revisionService) {}

    public function index(): View
    {
        return view('revisions.wip', [
            'pageTitle' => '我的修訂',
            'pageDescription' => '使用者修訂列表頁骨架已建立，後續會補上資料查詢與卡片列表。',
            'wipActions' => [],
        ]);
    }

    public function create(): View
    {
        return view('revisions.create');
    }

    public function store(StoreRevisionRequest $request): RedirectResponse
    {
        $revision = $this->revisionService->create(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('revisions.show', $revision)
            ->with('global', '修訂草稿建立完成');
    }

    public function show(string $revision): View
    {
        return view('revisions.wip', [
            'pageTitle' => '修訂詳情',
            'pageDescription' => '單一修訂詳情頁骨架已建立，後續會補上草稿編輯、action 清單與歷程。',
            'backRoute' => route('revisions.index'),
            'backLabel' => '返回我的修訂',
            'referenceId' => $revision,
            'wipActions' => ['update', 'submit', 'reopen', 'destroy'],
        ]);
    }

    public function update(Request $request, string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }

    public function submit(string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }

    public function reopen(string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }

    public function destroy(string $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }
}
