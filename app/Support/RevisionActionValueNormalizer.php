<?php

namespace App\Support;

use App\Enums\PropertyType;
use App\Enums\RevisionActionType;
use App\Models\EdgeType;
use App\Models\VertexType;

/**
 * Normalizes revision action `value` payloads to native JSON scalars / ENUM arrays (B1).
 */
class RevisionActionValueNormalizer
{
    public function __construct(private PropertyValueCaster $propertyValueCaster) {}

    /**
     * @param  array<string, mixed>  $actionData
     */
    public function normalize(array $actionData): mixed
    {
        if (! array_key_exists('value', $actionData) || $actionData['value'] === null) {
            return null;
        }

        $action = $actionData['action'] ?? null;
        $actionType = $action instanceof RevisionActionType
            ? $action
            : (is_string($action) ? RevisionActionType::tryFrom($action) : null);

        if ($actionType === null || ! is_string($actionData['age_property_name'] ?? null)) {
            return $actionData['value'];
        }

        $propertyType = $this->resolvePropertyType($actionType, $actionData['age_property_name']);
        if ($propertyType === null) {
            return $actionData['value'];
        }

        if (! $this->propertyValueCaster->matchesType($actionData['value'], $propertyType)) {
            // Leave as-is; RevisionValidationService reports the mismatch.
            return $actionData['value'];
        }

        $enumOptions = null;
        if ($propertyType === PropertyType::Enum) {
            $enumOptions = $this->resolveEnumOptions($actionType, $actionData['age_property_name']);
        }

        try {
            return $this->propertyValueCaster->toStorage($actionData['value'], $propertyType, $enumOptions);
        } catch (\InvalidArgumentException) {
            return $actionData['value'];
        }
    }

    private function resolvePropertyType(RevisionActionType $actionType, string $propertyName): ?PropertyType
    {
        $property = $this->findProperty($actionType, $propertyName);

        return $property?->age_property_type;
    }

    /**
     * @return list<array{value: string, label: string, active: bool}>|null
     */
    private function resolveEnumOptions(RevisionActionType $actionType, string $propertyName): ?array
    {
        $property = $this->findProperty($actionType, $propertyName);
        if ($property === null || ! is_array($property->enum_options)) {
            return null;
        }

        return EnumOptions::normalize($property->enum_options);
    }

    private function findProperty(RevisionActionType $actionType, string $propertyName): mixed
    {
        $isEdge = in_array($actionType, [
            RevisionActionType::CreateEdgeProperty,
            RevisionActionType::UpdateEdgeProperty,
            RevisionActionType::DeleteEdgeProperty,
        ], true);

        if ($isEdge) {
            foreach (EdgeType::query()->with('properties')->get() as $edgeType) {
                $property = $edgeType->properties->firstWhere('age_property_name', $propertyName);
                if ($property !== null) {
                    return $property;
                }
            }

            return null;
        }

        foreach (VertexType::query()->with('properties')->get() as $vertexType) {
            $property = $vertexType->properties->firstWhere('age_property_name', $propertyName);
            if ($property !== null) {
                return $property;
            }
        }

        return null;
    }
}
