<?php

namespace App\Mcp\Tools\Revision;

use App\Enums\RevisionStatus;
use App\Mcp\Concerns\AuthenticatesMcpRequests;
use App\Models\Revision;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-revisions')]
#[Description('搜尋目前登入使用者可查看的修訂，以便接續草稿或查詢送審狀態。')]
#[IsReadOnly]
class SearchRevisionsTool extends Tool
{
    use AuthenticatesMcpRequests;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $this->authenticatedUser($request);
        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', Rule::enum(RevisionStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = isset($validated['query']) ? trim($validated['query']) : null;
        $limit = (int) ($validated['limit'] ?? 20);
        $offset = (int) ($validated['offset'] ?? 0);

        $builder = Revision::query()
            ->withCount('actions')
            ->orderByDesc('updated_at');

        if ($user->hasPermission('revision.review')) {
            // reviewers can search the reviewable scope; still exclude unrelated drafts of others if desired?
            // Spec: 具有 revision.review 權限者可搜尋可審視範圍
            $builder->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('status', [
                        RevisionStatus::PendingReview->value,
                        RevisionStatus::Approved->value,
                        RevisionStatus::Rejected->value,
                    ]);
            });
        } else {
            $builder->where('user_id', $user->id);
        }

        if ($query !== null && $query !== '') {
            $like = '%'.mb_strtolower($query).'%';
            $builder->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$like]);
            });
        }

        if (! empty($validated['status'])) {
            $builder->where('status', $validated['status']);
        }

        $total = (clone $builder)->count();
        $revisions = $builder->skip($offset)->take($limit)->get()
            ->map(fn (Revision $revision): array => [
                'id' => $revision->id,
                'title' => $revision->title,
                'description' => $revision->description,
                'status' => $revision->status->value,
                'actions_count' => $revision->actions_count,
                'last_validation_is_valid' => $revision->last_validation_is_valid,
                'last_validation_summary' => $revision->last_validation_summary,
                'updated_at' => $revision->updated_at?->toIso8601String(),
            ])
            ->all();

        return Response::structured([
            'query' => $query,
            'total' => $total,
            'revisions' => $revisions,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('搜尋 title、description'),
            'status' => $schema->string()
                ->enum(['draft', 'pending_review', 'approved', 'rejected'])
                ->description('修訂狀態篩選'),
            'limit' => $schema->integer()
                ->description('預設 20，上限 50')
                ->default(20),
            'offset' => $schema->integer()
                ->description('分頁偏移')
                ->default(0),
        ];
    }
}
