<?php

namespace App\Http\Controllers;

use App\DataTables\UsersDataTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:user.manage');
    }

    public function index(UsersDataTable $dataTable): \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View
    {
        return $dataTable->render('user.index');
    }

    public function show(User $user): \Illuminate\Contracts\View\View
    {
        return view('user.show', compact('user'));
    }

    public function edit(User $user): \Illuminate\Contracts\View\View
    {
        $roles = Role::orderBy('id')->get();

        return view('user.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        // 禁止管理員移除自己的管理員角色
        $keepAdmin = false;
        if ($user->id == auth()->user()->id) {
            $keepAdmin = true;
        }

        DB::transaction(function () use ($request, $user, $keepAdmin) {
            // 移除原有權限
            $user->removeRole($user->roles);

            // 重新添加該有的權限
            if ($request->has('role')) {
                $user->addRoles($request->input('role'));
            }
            // 加回管理員
            if ($keepAdmin) {
                $admin = Role::where('name', '=', 'admin')->first();
                $user->addRoles($admin);
            }
        });

        return redirect()->route('user.show', $user)->with('global', '資料修改完成。');
    }

    public function destroy(User $user): \Illuminate\Http\RedirectResponse
    {
        if ($user->hasRole('admin')) {
            return redirect()->route('user.show', $user)->with('warning', '無法刪除管理員，請先解除管理員角色。');
        }
        $user->delete();

        return redirect()->route('user.index')->with('global', '會員已刪除。');
    }
}
