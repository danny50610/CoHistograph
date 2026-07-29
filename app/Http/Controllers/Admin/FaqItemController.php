<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaqItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:faq.manage');
    }

    public function index(): View
    {
        $faqItems = FaqItem::query()->ordered()->get();

        return view('admin.faq-items.index', compact('faqItems'));
    }

    public function create(): View
    {
        $nextSortOrder = (int) FaqItem::query()->max('sort_order') + 1;

        return view('admin.faq-items.create-or-edit', compact('nextSortOrder'));
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateFaqItem($request);

        $faqItem = FaqItem::query()->create($validated);

        return redirect()
            ->route('admin.faq-items.index')
            ->with('global', sprintf('常見問題「%s」已建立', $faqItem->question));
    }

    public function edit(FaqItem $faqItem): View
    {
        return view('admin.faq-items.create-or-edit', compact('faqItem'));
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $validated = $this->validateFaqItem($request);

        $faqItem->update($validated);

        return redirect()
            ->route('admin.faq-items.index')
            ->with('global', sprintf('常見問題「%s」已更新', $faqItem->question));
    }

    public function destroy(FaqItem $faqItem): RedirectResponse
    {
        $question = $faqItem->question;
        $faqItem->delete();

        return redirect()
            ->route('admin.faq-items.index')
            ->with('global', sprintf('常見問題「%s」已刪除', $question));
    }

    /**
     * @return array{question: string, answer: string, sort_order: int}
     *
     * @throws ValidationException
     */
    private function validateFaqItem(Request $request): array
    {
        /** @var array{question: string, answer: string, sort_order: int} $validated */
        $validated = $this->validate($request, [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        return $validated;
    }
}
