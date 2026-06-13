<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\CouldNotAcquireRevisionApplyLockException;
use App\Exceptions\RevisionApprovalValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRevisionRequest;
use App\Models\Revision;
use App\Services\Revision\RevisionReviewService;
use App\Services\Revision\RevisionValidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RevisionReviewController extends Controller
{
    public function __construct(
        private RevisionValidationService $revisionValidationService,
        private RevisionReviewService $revisionReviewService,
    ) {
        $this->middleware('permission:revision.review');
    }

    public function index(): View
    {
        $revisions = Revision::query()
            ->with(['user', 'reviews'])
            ->withCount('actions')
            ->orderByDesc('updated_at')
            ->paginate();

        return view('admin.revisions.index', compact('revisions'));
    }

    public function show(Revision $revision): View
    {
        $this->authorize('view', $revision);

        $revision->load([
            'user',
            'actions' => fn ($query) => $query->orderBy('order'),
            'reviews.actorUser',
        ]);

        $validationResult = $this->revisionValidationService->validate($revision);

        return view('admin.revisions.show', compact('revision', 'validationResult'));
    }

    public function approve(Revision $revision): RedirectResponse
    {
        $this->authorize('view', $revision);

        try {
            $this->revisionReviewService->approve($revision, Auth::user());
        } catch (CouldNotAcquireRevisionApplyLockException $exception) {
            return redirect()
                ->route('admin.revisions.show', $revision)
                ->withErrors(['lock' => $exception->getMessage()]);
        } catch (RevisionApprovalValidationException $exception) {
            $validationResult = $exception->validationResult();

            return redirect()
                ->route('admin.revisions.show', $revision)
                ->withErrors($validationResult->toMessageBag())
                ->with('revision_error_summary', $validationResult->summary())
                ->with('revision_action_errors', $validationResult->actionMessages());
        }

        return redirect()
            ->route('admin.revisions.show', $revision)
            ->with('global', '修訂已接受並套用');
    }

    public function reject(RejectRevisionRequest $request, Revision $revision): RedirectResponse
    {
        $this->authorize('view', $revision);

        $this->revisionReviewService->reject(
            $revision,
            $request->user(),
            $request->validated('comment'),
        );

        return redirect()
            ->route('admin.revisions.show', $revision)
            ->with('global', '修訂已退回');
    }
}
