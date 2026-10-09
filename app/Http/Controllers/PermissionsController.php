<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Per-user permissions. Roles are presets you can start from; what a person can
 * actually do is whatever is ticked against their account.
 */
class PermissionsController extends Controller
{
    public function index()
    {
        $knownCapabilities = config('capabilities.all');

        return Inertia::render('Permissions', [
            'catalogue' => config('capabilities.catalogue'),
            'presets' => collect(config('capabilities.roles'))
                ->map(fn ($caps, $role) => [
                    'role' => $role,
                    'label' => str($role)->replace('_', ' ')->title()->toString(),
                    'capabilities' => array_values($caps),
                ])->values(),
            'users' => User::withTrashed()->orderBy('name')->with('capabilityGrants')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'job_title' => $u->job_title,
                    'role' => $u->role,
                    'working_days' => $u->working_days ?: \App\Support\LeaveCalendar::DEFAULT_WORKING_DAYS,
                    'active' => ! $u->trashed(),
                    'leave_entitlement_days' => (float) (LeaveBalance::where('user_id', $u->id)
                        ->where('year', now()->year)->value('entitlement_days')
                        ?? LeaveBalance::defaultEntitlementFor($u)),
                    // Old deployments may have grants for capabilities that no
                    // longer exist. They must not inflate the visible count or
                    // be submitted back through the current permissions form.
                    'capabilities' => $u->capabilityGrants->pluck('capability')
                        ->intersect($knownCapabilities)->values(),
                ]),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'capabilities' => ['present', 'array'],
            'capabilities.*' => ['string', 'in:'.implode(',', config('capabilities.all'))],
        ]);

        $this->guardAgainstLockout($request, $user, $data['capabilities']);

        $user->syncCapabilities($data['capabilities'], $request->user());

        return back()->with('success', "Permissions updated for {$user->name}.");
    }

    /** Tick the boxes a preset would tick, without saving — the user confirms. */
    public function applyPreset(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_keys(config('capabilities.roles')))],
        ]);

        $capabilities = User::preset($data['role']);

        $this->guardAgainstLockout($request, $user, $capabilities);

        $user->syncCapabilities($capabilities, $request->user());
        $user->update(['role' => $data['role']]);

        return back()->with('success', sprintf(
            '%s now has the %s preset.',
            $user->name,
            str($data['role'])->replace('_', ' ')->title(),
        ));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', 'in:'.implode(',', array_keys(config('capabilities.roles')))],
            'working_days' => ['sometimes', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'leave_entitlement_days' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $existing = User::withTrashed()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();
        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages(['email' => 'An active account already uses this email address.']);
        }

        $leaveEntitlement = array_key_exists('leave_entitlement_days', $data)
            ? (float) $data['leave_entitlement_days'] : null;
        unset($data['leave_entitlement_days']);

        if ($existing) {
            $existing->restore();
            $existing->update([...$data, 'password' => Str::password(40)]);
            $existing->syncCapabilities(User::preset($data['role']), $request->user());
            $user = $existing;
        } else {
            $user = User::create([
                ...$data,
                // Staff use Microsoft SSO. A random local password means this
                // account cannot be entered with a shared/default credential.
                'password' => Str::password(40),
            ]);
        }

        LeaveBalance::updateOrCreate(
            ['user_id' => $user->id, 'year' => now()->year],
            ['entitlement_days' => $leaveEntitlement ?? LeaveBalance::defaultEntitlementFor($user)],
        );

        return back()->with('success', "Account ".($existing ? 'restored' : 'created')." for {$user->name}. They can now sign in with Microsoft using {$user->email}.");
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'job_title' => ['nullable', 'string', 'max:100'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'leave_entitlement_days' => ['required', 'numeric', 'between:0,100'],
        ]);

        $leaveEntitlement = (float) $data['leave_entitlement_days'];
        unset($data['leave_entitlement_days']);
        $data['working_days'] = array_values(array_unique(array_map('intval', $data['working_days'])));
        sort($data['working_days']);
        $user->update($data);
        LeaveBalance::updateOrCreate(
            ['user_id' => $user->id, 'year' => now()->year],
            ['entitlement_days' => $leaveEntitlement],
        );

        return back()->with('success', "Working pattern and leave allowance updated for {$user->name}.");
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');

        $this->guardAgainstLockout($request, $user, []);
        $user->delete();

        return back()->with('success', "{$user->name}'s account was deactivated.");
    }

    public function restoreUser(Request $request, int $user)
    {
        $account = User::onlyTrashed()->findOrFail($user);
        $account->restore();

        return back()->with('success', "{$account->name}'s account was restored.");
    }

    /**
     * Two ways to lock everybody out of the permissions screen: remove your own
     * access, or remove the last person who has it. Neither is recoverable from
     * inside the Hub, so both are refused.
     *
     * @param  array<int, string>  $capabilities
     */
    private function guardAgainstLockout(Request $request, User $user, array $capabilities): void
    {
        if (in_array('manage_settings', $capabilities, true)) {
            return;
        }

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'capabilities' => 'You cannot remove your own access to settings and permissions.',
            ]);
        }

        $othersWithSettings = User::whereKeyNot($user->id)
            ->whereHas('capabilityGrants', fn ($q) => $q->where('capability', 'manage_settings'))
            ->count();

        if ($othersWithSettings === 0) {
            throw ValidationException::withMessages([
                'capabilities' => "{$user->name} is the only person who can manage settings. Give someone else that permission first.",
            ]);
        }
    }
}
