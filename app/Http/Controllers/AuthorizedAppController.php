<?php

namespace App\Http\Controllers;

use App\Services\AuthorizedAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Passport\Client;

class AuthorizedAppController extends Controller
{
    public function __construct(
        private AuthorizedAppService $authorizedApps,
    ) {}

    public function index(Request $request): View
    {
        $apps = $this->authorizedApps->listForUser($request->user());

        return view('settings.authorized-apps', compact('apps'));
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $revoked = $this->authorizedApps->revokeForUser($request->user(), $client);

        if ($revoked === 0) {
            abort(404);
        }

        return redirect()
            ->route('settings.authorized-apps.index')
            ->with('global', "已撤銷「{$client->name}」的存取權限。");
    }
}
