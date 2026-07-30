<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SystemConfigKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHomepageConfigRequest;
use App\Services\SystemConfigService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomepageConfigController extends Controller
{
    public function __construct(
        private SystemConfigService $systemConfigService,
    ) {
        $this->middleware('permission:system-config.manage');
    }

    public function edit(): View
    {
        $homepage = $this->systemConfigService->homepage();

        return view('admin.system-config.homepage', compact('homepage'));
    }

    public function update(UpdateHomepageConfigRequest $request): RedirectResponse
    {
        $this->systemConfigService->put(
            SystemConfigKey::Homepage,
            $request->validated(),
        );

        return redirect()
            ->route('admin.system-config.homepage.edit')
            ->with('global', '首頁設定已更新');
    }
}
