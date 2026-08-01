<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('explore-vertex-context')]
#[Description('Explore an existing vertex and its neighborhood in the knowledge graph (read-only). Use when the user wants to understand what is already recorded about a person, event, or other entity before editing.')]
class ExploreVertexContextPrompt extends Prompt
{
    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:100'],
            'vertex_type_label' => ['nullable', 'string', 'max:255'],
        ], [
            'query.required' => 'Provide a search keyword (e.g. a person or event name).',
            'query.min' => 'Provide a search keyword (e.g. a person or event name).',
        ]);

        $query = trim($validated['query']);
        $vertexTypeLabel = isset($validated['vertex_type_label'])
            ? trim($validated['vertex_type_label'])
            : null;

        $typeHint = ($vertexTypeLabel !== null && $vertexTypeLabel !== '')
            ? "Focus on VertexType age_label_name \"{$vertexTypeLabel}\"."
            : 'If the VertexType is unknown, call search-vertex-types first (include_properties=true), then pick the best age_label_name.';

        $userMessage = <<<TEXT
Explore the knowledge-graph context for: "{$query}".

{$typeHint}

Follow this read-only workflow using CoHistograph tools:
1. search-vertices with the query (and vertex_type_label when known) to find candidate age_id values.
2. For the best match(es), call get-vertex-detail.
3. Call list-vertex-neighbors on the chosen age_id to map connected edges and neighboring vertices.
4. If an edge matters, call get-edge-detail or search-edges as needed.

Then summarize in plain language:
- Which vertex you selected (age_id, label, key properties)
- Important neighboring vertices and relationship types
- Gaps or ambiguities (multiple matches, missing properties, sparse neighborhood)

Do not create or edit revisions unless the user explicitly asks next. This prompt is for exploration only.
TEXT;

        return [
            Response::text(
                'You are exploring a collaborative historical-event knowledge graph. Prefer CoHistograph read-only tools (search-vertices, get-vertex-detail, list-vertex-neighbors, search-edges, get-edge-detail, search-vertex-types). Never write Apache AGE directly.'
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
                name: 'query',
                description: 'Search keyword for the vertex to explore (e.g. a person or event name).',
                required: true,
            ),
            new Argument(
                name: 'vertex_type_label',
                description: 'Optional VertexType age_label_name to scope the search (e.g. person, event).',
                required: false,
            ),
        ];
    }
}
