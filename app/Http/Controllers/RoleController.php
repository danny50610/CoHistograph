<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:role.manage');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $roles = Role::orderBy('id')->get();
        $permissions = Permission::with('roles')->orderBy('id')->get();

        return view('role.index', compact('roles', 'permissions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $permissions = Permission::orderBy('id')->get();

        return view('role.create-or-edit', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name'         => 'required|unique:roles,name',
            'display_name' => 'required',
            'permissions'  => 'array',
        ]);

        /** @var Role $role */
        $role = Role::create([
            'name'         => $request->input('name'),
            'display_name' => $request->input('display_name'),
            'protection'   => false,
        ]);
        $role->permissions()->sync($request->input('permissions') ?: []);

        return redirect()->route('role.index')->with('global', sprintf('角色「%s」已建立', $role->name));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Role $role
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(Role $role)
    {
        $permissions = Permission::orderBy('id')->get();

        return view('role.create-or-edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Role $role
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, Role $role)
    {
        $this->validate($request, [
            'name'         => 'required|unique:roles,name,' . $role->id . ',id',
            'display_name' => 'required',
            'permissions'  => 'array',
        ]);

        if ($role->protection) {
            $role->update([
                'display_name' => $request->input('display_name'),
            ]);
        } else {
            $role->update([
                'name'         => $request->input('name'),
                'display_name' => $request->input('display_name'),
            ]);
            $role->permissions()->sync($request->input('permissions') ?: []);
        }

        return redirect()->route('role.index')->with('global', sprintf('角色「%s」已更新', $role->name));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Role $role
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(Role $role)
    {
        if ($role->protection) {
            return back()->with('warning', '無法刪除受保護角色');
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('role.index')->with('global', sprintf('角色「%s」已刪除', $roleName));
    }
}
