<?php

use App\Models\VertexType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $locales = array_keys(config('cohistograph.app.graph.locales', []));

        VertexType::query()
            ->whereNotNull('show_property_name')
            ->with('properties')
            ->chunkById(100, function ($vertexTypes) use ($locales): void {
                foreach ($vertexTypes as $vertexType) {
                    $showPropertyName = $vertexType->show_property_name;

                    if (! is_string($showPropertyName) || $showPropertyName === '') {
                        continue;
                    }

                    foreach ($locales as $locale) {
                        $suffix = '_'.$locale;

                        if (! Str::endsWith($showPropertyName, $suffix)) {
                            continue;
                        }

                        $baseName = Str::beforeLast($showPropertyName, $suffix);
                        $propertyExists = $vertexType->properties->contains(
                            fn ($property) => $property->age_property_name === $showPropertyName
                                && $property->locale === $locale,
                        );

                        if ($propertyExists) {
                            $vertexType->update(['show_property_name' => $baseName]);
                        }

                        break;
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data migration.
    }
};
