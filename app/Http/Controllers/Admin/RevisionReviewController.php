<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Revision;
use App\Services\Revision\RevisionValidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RevisionReviewController extends Controller
{
    public function __construct(private RevisionValidationService $revisionValidationService)
    {
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
        throw new \Exception('Not impl.');
    }

    public function reject(Request $request, Revision $revision): RedirectResponse
    {
        throw new \Exception('Not impl.');
    }
}
