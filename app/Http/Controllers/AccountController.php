<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ImageResizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /** The account settings page (profile / security tabs). */
    public function edit(Request $request)
    {
        $tab = in_array($request->query('tab'), ['profile', 'security'], true)
            ? $request->query('tab')
            : 'profile';

        return view('account.index', ['tab' => $tab]);
    }

    /** Update name, email, and avatar. */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:4096'], // 4 MB
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];

        if ($request->boolean('remove_avatar')) {
            $this->deleteAvatarFile($user->avatar);
            $user->avatar = null;
        }

        if ($request->hasFile('avatar')) {
            $dir = public_path('uploads/avatars');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = Str::random(32).'.png';
            if (ImageResizer::fitToPng($request->file('avatar')->getPathname(), $dir.'/'.$name, 256)) {
                $this->deleteAvatarFile($user->avatar);
                $user->avatar = $name;
            }
        }

        $user->save();

        return redirect()->route('account', ['tab' => 'profile'])->with('status', 'Your profile has been updated.');
    }

    /** Change the password (requires the current password). */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'min:8', 'different:current_password'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
        ]);

        $request->user()->update(['password' => Hash::make($request->password)]);

        return redirect()->route('account', ['tab' => 'security'])->with('status', 'Your password has been changed.');
    }

    /** Permanently delete the account and the content it owns. */
    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'The password is incorrect.',
        ]);

        $user = $request->user();

        // Never let the last active administrator lock everyone out of the panel.
        if ($user->isAdmin() && User::where('role', 'admin')
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->doesntExist()) {
            return back()->with('error', 'You are the only administrator. Promote another admin before deleting your account.');
        }

        $this->deleteAvatarFile($user->avatar);

        // No DB foreign keys here (app-level integrity), so clean up owned rows explicitly.
        // "tokens" are the Sanctum API tokens; revoking them prevents orphaned credentials.
        foreach (['links', 'qrCodes', 'qrTemplates', 'bioPages', 'domains', 'pixels', 'tokens', 'webhooks', 'subscriptions', 'payments', 'tickets'] as $relation) {
            $user->{$relation}()->delete();
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been permanently deleted.');
    }

    private function deleteAvatarFile(?string $avatar): void
    {
        if ($avatar) {
            @unlink(public_path('uploads/avatars/'.$avatar));
        }
    }
}
