<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Menus\MenuBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    public function __construct(private MenuBuilder $menus) {}

    public function index()
    {
        Gate::authorize('admin.menus.view');

        return to_route('admin.menus.edit', array_key_first(config('menus.menus')));
    }

    public function edit(Request $request, string $key)
    {
        Gate::authorize('admin.menus.view');

        $menu = $this->menus->menu($key);

        return view('admin.menus.edit', [
            'menu' => $menu,
            'config' => $this->menus->config($key),
            'menus' => config('menus.menus'),
            'rows' => $this->menus->editorRows($menu),
            'version' => $this->menus->version($menu),
            'canEdit' => $request->user()->can('admin.menus.update'),
        ]);
    }

    public function update(Request $request, string $key)
    {
        Gate::authorize('admin.menus.update');

        $menu = $this->menus->menu($key);

        if (! hash_equals($this->menus->version($menu), (string) $request->input('version'))) {
            return response()->json(['message' => 'This menu was changed somewhere else (another tab or another admin). Reload the page to see the latest version before saving.', 'stale' => true], 409);
        }

        [$rows, $errors] = $this->menus->validate($menu, $request->all());

        if ($errors) {
            return response()->json(['message' => 'Some links need fixing before saving.', 'errors' => $errors], 422);
        }

        $this->menus->save($menu, $rows);

        return response()->json([
            'message' => "{$menu->name} saved. The website shows it now.",
            'rows' => $this->menus->editorRows($menu->fresh()),
            'version' => $this->menus->version($menu->fresh()),
        ]);
    }
}
