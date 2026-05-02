<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevisionRequest;
use App\Http\Requests\UpdateRevisionRequest;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\VertexType;
use App\Services\RevisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RevisionController extends Controller
{
    public function __construct(private RevisionService $revisionService) {}

    public function index(): View
    {
        $revisions = Revision::where('user_id', auth()->id())
            ->withCount('actions')
            ->with('reviews')
            ->orderByDesc('updated_at')
            ->paginate();

        return view('revisions.index', compact('revisions'));
    }

    public function create(): View
    {
        return view('revisions.create-or-edit');
    }

    public function store(StoreRevisionRequest $request): RedirectResponse
    {
        $revision = $this->revisionService->create(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('revisions.edit', $revision)
            ->with('global', '修訂草稿建立完成，請繼續新增操作');
    }

    public function show(Revision $revision): View
    {
        $this->authorize('view', $revision);

        $revision->load([
            'user',
            'actions' => fn ($q) => $q->orderBy('order'),
            'reviews.actorUser',
        ]);

        return view('revisions.show', compact('revision'));
    }

    public function edit(Revision $revision): View
    {
        $this->authorize('update', $revision);

        abort_unless($revision->isDraft(), 403, '只有草稿狀態可以編輯');

        $revision->load([
            'actions' => fn ($q) => $q->orderBy('order'),
        ]);

        $vertexTypes = VertexType::orderBy('name')->get();
        $edgeTypes = EdgeType::with(['startVertex', 'endVertex'])->orderBy('name')->get();

        return view('revisions.create-or-edit', compact(
            'revision',
            'vertexTypes',
            'edgeTypes',
        ));
    }

    public function update(UpdateRevisionRequest $request, Revision $revision): RedirectResponse
    {
        $this->authorize('update', $revision);

        abort_unless($revision->isDraft(), 403, '只有草稿狀態可以更新');

        $this->revisionService->update($revision, $request->validated());

        return redirect()
            ->route('revisions.show', $revision)
            ->with('global', '修訂已儲存');
    }

    public function submit(Revision $revision): RedirectResponse
    {
        $this->authorize('update', $revision);

        $this->revisionService->submit($revision);

        return redirect()
            ->route('revisions.show', $revision)
            ->with('global', '修訂已提交審核');
    }

    public function reopen(Revision $revision): RedirectResponse
    {
        $this->authorize('update', $revision);

        $this->revisionService->reopen($revision);

        return redirect()
            ->route('revisions.show', $revision)
            ->with('global', '修訂已重新開啟');
    }

    public function destroy(Revision $revision): RedirectResponse
    {
        $this->authorize('delete', $revision);

        $this->revisionService->destroy($revision);

        return redirect()
            ->route('revisions.index')
            ->with('global', '修訂已刪除');
    }
}
