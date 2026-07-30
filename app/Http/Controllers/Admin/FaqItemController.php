<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        $placementOptions = $this->placementOptions();
        $placeAfterId = $placementOptions === []
            ? null
            : (string) $placementOptions[array_key_last($placementOptions)]['value'];

        return view('admin.faq-items.create-or-edit', compact('placementOptions', 'placeAfterId'));
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateFaqItem($request);

        $faqItem = DB::transaction(function () use ($validated) {
            $faqItem = FaqItem::query()->create([
                'question' => $validated['question'],
                'answer' => $validated['answer'],
                'is_hidden' => $validated['is_hidden'],
                'sort_order' => 0,
            ]);

            $this->applyPlacement($faqItem, $validated['place_after_id']);

            return $faqItem;
        });

        return redirect()
            ->route('admin.faq-items.index')
            ->with('global', sprintf('常見問題「%s」已建立', $faqItem->question));
    }

    public function edit(FaqItem $faqItem): View
    {
        $placementOptions = $this->placementOptions($faqItem);
        $placeAfterId = $this->currentPlaceAfterId($faqItem);

        return view('admin.faq-items.create-or-edit', compact('faqItem', 'placementOptions', 'placeAfterId'));
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $validated = $this->validateFaqItem($request, $faqItem);

        DB::transaction(function () use ($faqItem, $validated) {
            $faqItem->update([
                'question' => $validated['question'],
                'answer' => $validated['answer'],
                'is_hidden' => $validated['is_hidden'],
            ]);

            $this->applyPlacement($faqItem, $validated['place_after_id']);
        });

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
     * @return array{question: string, answer: string, is_hidden: bool, place_after_id: int|null}
     *
     * @throws ValidationException
     */
    private function validateFaqItem(Request $request, ?FaqItem $faqItem = null): array
    {
        /** @var array{question: string, answer: string, is_hidden?: string, place_after_id?: string|null} $validated */
        $validated = $this->validate($request, [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'is_hidden' => ['sometimes', 'boolean'],
            'place_after_id' => [
                'nullable',
                'integer',
                Rule::exists('faq_items', 'id')->when(
                    $faqItem !== null,
                    fn ($rule) => $rule->whereNot('id', $faqItem->id)
                ),
            ],
        ]);

        return [
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'is_hidden' => $request->boolean('is_hidden'),
            'place_after_id' => isset($validated['place_after_id'])
                ? (int) $validated['place_after_id']
                : null,
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function placementOptions(?FaqItem $excluding = null): array
    {
        return FaqItem::query()
            ->ordered()
            ->when($excluding !== null, fn ($query) => $query->whereKeyNot($excluding->id))
            ->get()
            ->map(fn (FaqItem $item): array => [
                'value' => $item->id,
                'label' => sprintf('「%s」下方', $item->question),
            ])
            ->values()
            ->all();
    }

    private function currentPlaceAfterId(FaqItem $faqItem): ?string
    {
        $ordered = FaqItem::query()->ordered()->get();
        $index = $ordered->search(fn (FaqItem $item): bool => $item->id === $faqItem->id);

        if ($index === false || $index === 0) {
            return null;
        }

        return (string) $ordered[$index - 1]->id;
    }

    private function applyPlacement(FaqItem $faqItem, ?int $placeAfterId): void
    {
        $others = FaqItem::query()
            ->whereKeyNot($faqItem->id)
            ->ordered()
            ->get();

        $ordered = collect();

        if ($placeAfterId === null) {
            $ordered->push($faqItem);
            $ordered = $ordered->concat($others);
        } else {
            $inserted = false;

            foreach ($others as $other) {
                $ordered->push($other);

                if ($other->id === $placeAfterId) {
                    $ordered->push($faqItem);
                    $inserted = true;
                }
            }

            if (! $inserted) {
                $ordered->push($faqItem);
            }
        }

        foreach ($ordered->values() as $index => $item) {
            $sortOrder = $index + 1;

            if ($item->sort_order !== $sortOrder) {
                FaqItem::query()->whereKey($item->id)->update(['sort_order' => $sortOrder]);
            }
        }
    }
}
