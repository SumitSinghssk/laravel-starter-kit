<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActiveSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        Gate::authorize('profile.view');

        return view('admin.auth.profile', [
            'user' => $request->user(),
            'sessions' => app(ActiveSessions::class)->forUser($request->user(), $request->session()->getId()),
        ]);
    }

    public function update(Request $request)
    {
        Gate::authorize('profile.update');

        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|url',
            'remove_avatar' => 'nullable|boolean',
        ]);

        if ($request->remove_avatar) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request)
    {
        Gate::authorize('profile.update-password');

        $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('status', 'password-updated');
    }
}
