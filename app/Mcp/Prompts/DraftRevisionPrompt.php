<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('draft-revision')]
#[Description('Guided workflow to draft a knowledge-graph revision: look up schema and existing graph data, create a draft, add actions one at a time, validate, then ask before submit. Use when the user wants to add or change vertices/edges via the Revision workflow.')]
class DraftRevisionPrompt extends Prompt
{
    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'goal' => ['required', 'string', 'min:1', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ], [
            'goal.required' => 'Describe the intended change (e.g. "add the Xinhai Revolution event and link Sun Yat-sen as participant").',
            'goal.min' => 'Describe the intended change (e.g. "add the Xinhai Revolution event and link Sun Yat-sen as participant").',
        ]);

        $goal = trim($validated['goal']);
        $title = isset($validated['title']) ? trim($validated['title']) : null;

        $titleHint = ($title !== null && $title !== '')
            ? "Suggested revision title: \"{$title}\". Use it for create-revision unless the user prefers another."
            : 'Derive a concise revision title from the goal when calling create-revision.';

        $userMessage = <<<TEXT
Draft a knowledge-graph revision for this goal:
{$goal}

{$titleHint}

Follow this CoHistograph workflow using tools (never write Apache AGE directly):

1. Schema — call search-vertex-types and/or search-edge-types with include_properties=true to learn age_label_name and age_property_name for the types you need.
2. Existing data — search-vertices / search-edges / get-vertex-detail / get-edge-detail / list-vertex-neighbors to find existing target_age_id values and avoid duplicates when the entity already exists.
3. Continue vs create — search-revisions with status=draft (and rejected if relevant). If a matching draft already covers this goal, continue that revision_id instead of creating another. Otherwise create-revision with title (and optional description).
4. Edit one action at a time — use add-revision-action / update-revision-action / delete-revision-action / move-revision-action. Never overwrite the full actions list. Prefer create_vertex then create_vertex_property for new vertices; use target_ref_order when referencing a vertex/edge created earlier in the same revision.
5. Validate — after edits (especially moves that affect *_ref_order), call validate-revision. Fix action_errors / general_errors, then re-validate until is_valid is true.
6. Submit only with confirmation — summarize the draft (revision id, title, actions, validation). Ask the user before submit-revision. Do not submit unless they explicitly agree.

If a revision was rejected and they want to continue it: reopen-revision first, then edit as above.

Report progress in plain language after major steps (schema found, draft created, actions added, validation status).
TEXT;

        return [
            Response::text(
                'You are drafting a collaborative historical-event knowledge-graph revision. Prefer CoHistograph tools. Never write Apache AGE directly — all graph changes go through the Revision workflow. Revisions created or edited via MCP are marked is_ai_assisted=true.'
            )->asAssistant(),
            Response::text($userMessage),
        ];
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'goal',
                description: 'What change to draft (e.g. add an event, update a person property, create an edge between entities).',
                required: true,
            ),
            new Argument(
                name: 'title',
                description: 'Optional suggested revision title for create-revision.',
                required: false,
            ),
        ];
    }
}
