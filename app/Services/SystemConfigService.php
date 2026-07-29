<?php

namespace App\Services;

use App\Enums\SystemConfigKey;
use App\Models\SystemConfig;
use App\SystemConfig\HomepageConfig;
use App\SystemConfig\SystemConfigValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SystemConfigService
{
    public function homepage(): HomepageConfig
    {
        $value = $this->get(SystemConfigKey::Homepage);

        assert($value instanceof HomepageConfig);

        return $value;
    }

    public function get(SystemConfigKey $key): SystemConfigValue
    {
        $class = $key->valueClass();
        $row = SystemConfig::query()->where('key', $key->value)->first();

        if ($row === null) {
            return $class::defaults();
        }

        $validator = Validator::make(
            is_array($row->value) ? $row->value : [],
            $class::rules(),
        );

        if ($validator->fails()) {
            return $class::defaults();
        }

        return $class::fromValidated($validator->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function put(SystemConfigKey $key, array $data): SystemConfigValue
    {
        $class = $key->valueClass();
        $validated = Validator::make($data, $class::rules())->validate();
        $value = $class::fromValidated($validated);

        SystemConfig::query()->updateOrCreate(
            ['key' => $key->value],
            ['value' => $value->toArray()],
        );

        return $value;
    }
}
