<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        // Determine active role (your existing switch)
        $activeRole = session('active_role', $user->is_coach ? 'coach' : 'client');

        // Safety: if user isn't coach, force client mode
        if ($activeRole === 'coach' && empty($user->is_coach)) {
            $activeRole = 'client';
        }

        return view('profile.edit', compact('user', 'activeRole'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $activeRole = session('active_role', $user->is_coach ? 'coach' : 'client');
        $isCoachMode = ($activeRole === 'coach' && !empty($user->is_coach));
        // dd($request->hasFile('gallery'), $request->file('gallery'), session('active_role'), $user->is_coach);


        $rules = [
            'first_name'    => [$isCoachMode ? 'required' : 'nullable', 'string', 'max:80'],
            'last_name'     => ['nullable','string','max:80'],
            'email'         => ['required','email', Rule::unique('users','email')->ignore($user->id)],

            'phone_code'    => ['nullable','string','max:10'],
            'phone'         => ['nullable','string','max:30'],

            // unify max lengths (you had diff client/coach limits)
            'short_bio'     => ['nullable','string','max:160'],
            'description'   => ['nullable','string','max:6000'],

            'country'       => ['nullable','string','max:120'],
            'city'          => ['nullable','string','max:120'],
            'timezone'      => ['nullable','string','max:120'],

            'languages'     => ['nullable','array'],
            'languages.*'   => ['nullable','string','max:40'],

            // socials
            'facebook_url'  => ['nullable','url'],
            'instagram_url' => ['nullable','url'],
            'linkedin_url'  => ['nullable','url'],
            'twitter_url'   => ['nullable','url'],
            'youtube_url'   => ['nullable','url'],

            // avatar
            'avatar'         => ['nullable','image','max:10240'],
            'avatar_cropped' => ['nullable','string'],
        ];

        // Coach-only rules (only when truly in coach mode)
        if ($isCoachMode) {
            $rules = array_merge($rules, [
                'service_areas'    => ['nullable','array'],
                'service_areas.*'  => ['nullable','string','max:80'],

                'gallery'          => ['nullable','array'],
                'gallery.*'        => ['image','max:4096'],
                'gallery_delete'   => ['nullable','array'],
                'gallery_delete.*' => ['nullable','string'],

                'qual_title'       => ['nullable','array'],
                'qual_date'        => ['nullable','array'],
                'qual_desc'        => ['nullable','array'],
            ]);
        }

        $data = $request->validate($rules);

        /* ===================== Avatar (prefer cropped) ===================== */
        if ($request->filled('avatar_cropped')) {
            $dataUrl = $request->input('avatar_cropped');

            if (str_starts_with($dataUrl, 'data:image')) {
                [, $encoded] = explode(',', $dataUrl, 2);
            } else {
                $encoded = $dataUrl;
            }

            $decoded = base64_decode($encoded);

            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $filename = 'avatars/' . uniqid($isCoachMode ? 'coach_avatar_' : 'avatar_') . '.jpg';
            Storage::disk('public')->put($filename, $decoded);

            $data['avatar_path'] = $filename;

        } elseif ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        /* ===================== Normalize arrays ===================== */
        // languages
        if ($request->filled('languages')) {
            $langs = array_values(array_filter($request->input('languages', []), fn($v) => filled($v)));
            $data['languages'] = $langs ?: null;
        }

        // common blanks => null
        foreach ([
            'facebook_url','instagram_url','linkedin_url','twitter_url','youtube_url',
            'city','country','timezone','short_bio','description','phone','phone_code'
        ] as $k) {
            if (isset($data[$k]) && $data[$k] === '') $data[$k] = null;
        }

        /* ===================== Coach-only fields ===================== */
        if ($isCoachMode) {
            // service areas
            $areas = array_values(array_filter($request->input('service_areas', []), fn($v)=>filled($v)));
            $data['coach_service_areas'] = $areas ?: null;

            // qualifications
            $qual = [];
            $titles = $request->input('qual_title', []);
            $dates  = $request->input('qual_date', []);
            $descs  = $request->input('qual_desc', []);
            $count  = max(count($titles), count($dates), count($descs));

            for ($i=0; $i<$count; $i++) {
                if (filled($titles[$i] ?? null) || filled($dates[$i] ?? null) || filled($descs[$i] ?? null)) {
                    $qual[] = [
                        'title'       => trim($titles[$i] ?? ''),
                        'achieved_at' => trim($dates[$i]  ?? ''),
                        'description' => trim($descs[$i]  ?? ''),
                    ];
                }
            }
            $data['coach_qualifications'] = $qual ?: null;

            // gallery: keep existing, remove deletes, append new
            $existing = (array) ($user->coach_gallery ?? []);
            $keep = $existing;

            $toDelete = $request->input('gallery_delete', []);
            if (!empty($toDelete)) {
                foreach ($toDelete as $path) {
                    $path = trim($path);
                    if (!$path) continue;

                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }

                    $keep = array_values(array_filter($keep, fn($p) => $p !== $path));
                }
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    $keep[] = $file->store('gallery', 'public');
                }
            }

            $data['coach_gallery'] = $keep ?: null;
        }

        /* ===================== Persist ===================== */
        // If you want email/timezone locked like your coach code did, exclude them here.
        // Right now: both can update email, timezone if you keep the fields enabled.
        $user->update(
            
            Arr::except($data, [
                'avatar', 'avatar_cropped',
                'gallery', 'gallery_delete',
                'service_areas',
                'qual_title', 'qual_date', 'qual_desc',
            ])
            
        );

        return back()->with('ok', __('Profile Updated Successfully.'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required','current_password'],
            'password'         => ['required','min:8','confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('ok', __('Password Updated.'));
    }

    public function deactivate(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('ok', __('Your account has been deactivated (stub).'));
    }
}
