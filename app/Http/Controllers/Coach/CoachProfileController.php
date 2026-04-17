<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CoachProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        $countries = ['Pakistan','Afghanistan','India','United Arab Emirates','Saudi Arabia'];
        $timezones = \DateTimeZone::listIdentifiers();
        $phoneCodes = [
            ['code'=>'+92','label'=>'Pakistan (+92)'],
            ['code'=>'+93','label'=>'Afghanistan (+93)'],
            ['code'=>'+91','label'=>'India (+91)'],
            ['code'=>'+971','label'=>'UAE (+971)'],
            ['code'=>'+966','label'=>'Saudi Arabia (+966)'],
        ];

        return view('coach.profile.edit', compact('user','countries','timezones','phoneCodes'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'first_name'    => ['required','string','max:80'],
            'last_name'     => ['nullable','string','max:80'],
            'email'         => ['required','email', Rule::unique('users','email')->ignore($user->id)],
            'phone_code'    => ['nullable','string','max:10'],
            'phone'         => ['nullable','string','max:30'],

            'short_bio'     => ['nullable','string','max:150'],
            'description'   => ['nullable','string','max:5000'],
            'timezone'      => ['nullable','string','max:120'],
            'country'       => ['nullable','string','max:120'],
            'city'          => ['nullable','string','max:120'],

            'languages'     => ['nullable','array'],
            'languages.*'   => ['nullable','string','max:40'],

            'service_areas' => ['nullable','array'],
            'service_areas.*' => ['nullable','string','max:80'],

            // avatar + gallery
            'avatar'        => ['nullable','image','max:4096'],
            'avatar_cropped' => ['nullable','string'],
            'gallery'       => ['nullable','array'],
            'gallery.*'     => ['image','max:4096'],

            'gallery_delete'   => ['nullable','array'],
'gallery_delete.*' => ['nullable','string'],

            // qualifications repeater
            'qual_title'    => ['nullable','array'],
            'qual_date'     => ['nullable','array'],
            'qual_desc'     => ['nullable','array'],

            // socials
            'facebook_url'  => ['nullable','url'],
            'instagram_url' => ['nullable','url'],
            'linkedin_url'  => ['nullable','url'],
            'twitter_url'   => ['nullable','url'],
            'youtube_url'   => ['nullable','url'],
        ];

        $data = $request->validate($rules);

        // avatar
       // avatar (prefer cropped, then fallback to raw file)
if ($request->filled('avatar_cropped')) {
    $dataUrl = $request->input('avatar_cropped'); // "data:image/jpeg;base64,..."

    // strip "data:image/xxx;base64," if present
    if (str_starts_with($dataUrl, 'data:image')) {
        [$meta, $encoded] = explode(',', $dataUrl, 2);
    } else {
        $encoded = $dataUrl;
    }

    $decoded = base64_decode($encoded);

    // delete old avatar if exists
    if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
        Storage::disk('public')->delete($user->avatar_path);
    }

    $filename = 'avatars/' . uniqid('coach_avatar_') . '.jpg';
    Storage::disk('public')->put($filename, $decoded);

    $data['avatar_path'] = $filename;

} elseif ($request->hasFile('avatar')) {
    // fallback: normal file upload (no crop)
    if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
        Storage::disk('public')->delete($user->avatar_path);
    }

    $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
}


        // gallery (append to existing)
       // gallery: start from existing
$existing = (array) ($user->coach_gallery ?? []);
// TEMP DEBUG


$keep     = $existing;

// 1) remove ones user requested to delete
$toDelete = $request->input('gallery_delete', []);
if (!empty($toDelete)) {
    foreach ($toDelete as $path) {
        $path = trim($path);
        if (!$path) continue;

        // delete file from storage if exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        // remove from $keep array
        $keep = array_values(array_filter($keep, fn($p) => $p !== $path));
    }
}

// 2) append new uploads
if ($request->hasFile('gallery')) {
    foreach ($request->file('gallery') as $file) {
        $keep[] = $file->store('gallery', 'public');
    }
}

$data['coach_gallery'] = $keep ?: null;


        // service areas
        $areas = array_values(array_filter($request->input('service_areas', []), fn($v)=>filled($v)));
        $data['coach_service_areas'] = $areas ?: null;

        // languages
        $langs = array_values(array_filter($request->input('languages', []), fn($v)=>filled($v)));
        $data['languages'] = $langs ?: null;

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

        // normalize blanks to null
        foreach (['facebook_url','instagram_url','linkedin_url','twitter_url','youtube_url','city','country','timezone','short_bio','description','phone','phone_code'] as $k) {
            if (isset($data[$k]) && $data[$k]==='') $data[$k]=null;
        }

        $user->update(Arr::except(
            $data,
            ['avatar', 'avatar_cropped', 'gallery','gallery_delete', 'qual_title', 'qual_date', 'qual_desc', 'email', 'timezone']
        ));
        

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
        // soft “deactivate” stub: simply logout for now
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('ok', __('Your account has been deactivated (stub).'));
    }
}
