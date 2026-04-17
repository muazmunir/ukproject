<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ClientProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        // fallback demo user if not logged in (optional for front-end testing)
        if (!$user) {
            $user = (object)[
                'first_name' => 'Muhammad',
                'last_name'  => 'Arif',
                'email'      => 'example@domain.com',
                'phone_code' => '+92',
                'phone'      => '03001234567',
                'short_bio'  => 'Bio goes here…',
                'description'=> 'Some longer description…',
                'country'    => 'Pakistan',
                'city'       => 'Karachi',
                'timezone'   => 'Asia/Karachi',
                'languages'  => ['English','Urdu'],
                'facebook_url'  => null,
                'instagram_url' => null,
                'linkedin_url'  => null,
                'twitter_url'   => null,
                'youtube_url'   => null,
                'avatar_path'   => null,
            ];
        }

        $countries = ['Pakistan','Afghanistan','India','United Arab Emirates','Saudi Arabia'];
        $timezones = \DateTimeZone::listIdentifiers();
        $phoneCodes = [
            ['code'=>'+92','label'=>'Pakistan (+92)'],
            ['code'=>'+93','label'=>'Afghanistan (+93)'],
            ['code'=>'+91','label'=>'India (+91)'],
            ['code'=>'+971','label'=>'UAE (+971)'],
            ['code'=>'+966','label'=>'Saudi Arabia (+966)'],
        ];

        return view('client.profile-edit', compact('user','countries','timezones','phoneCodes'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'first_name'    => ['nullable','string','max:80'],
            'last_name'     => ['nullable','string','max:80'],
            'email'         => ['required','email', Rule::unique('users','email')->ignore($user->id)],
            'phone_code'    => ['nullable','string','max:10'],
            'phone'         => ['nullable','string','max:30'],
            'short_bio'     => ['nullable','string','max:160'],
            'description'   => ['nullable','string','max:6000'],
            'country'       => ['nullable','string','max:120'],
            'city'          => ['nullable','string','max:120'],
            'timezone'      => ['nullable','string','max:120'],
            'languages'     => ['nullable','array'],
            'languages.*'   => ['nullable','string','max:40'],
            'facebook_url'  => ['nullable','url'],
            'instagram_url' => ['nullable','url'],
            'linkedin_url'  => ['nullable','url'],
            'twitter_url'   => ['nullable','url'],
            'youtube_url'   => ['nullable','url'],
            'avatar'        => ['nullable','image','max:2048'],
            'avatar_cropped' => ['nullable','string'],
        ];

        $data = $request->validate($rules);

       // Prefer cropped avatar if present, otherwise fallback to raw upload
if ($request->filled('avatar_cropped')) {
    $dataUrl = $request->input('avatar_cropped'); // "data:image/jpeg;base64,..."

    // strip "data:image/xxx;base64," if present
    if (str_starts_with($dataUrl, 'data:image')) {
        [$meta, $encoded] = explode(',', $dataUrl, 2);
    } else {
        $encoded = $dataUrl;
    }

    $decoded = base64_decode($encoded);

    // delete old avatar if any
    if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
        Storage::disk('public')->delete($user->avatar_path);
    }

    $filename = 'avatars/' . uniqid('avatar_') . '.jpg';
    Storage::disk('public')->put($filename, $decoded);

    $data['avatar_path'] = $filename;

} elseif ($request->hasFile('avatar')) {
    // fallback: no crop (e.g. JS disabled)
    $file = $request->file('avatar');

    // delete old avatar if any
    if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
        Storage::disk('public')->delete($user->avatar_path);
    }

    $path = $file->store('avatars', 'public');
    $data['avatar_path'] = $path;
}


        foreach (['facebook_url','instagram_url','linkedin_url','twitter_url','youtube_url','city','country','timezone','short_bio','description','phone','phone_code'] as $k) {
            if (isset($data[$k]) && $data[$k] === '') $data[$k] = null;
        }

        if ($request->filled('languages')) {
            $langs = array_values(array_filter($request->input('languages', []), fn($v)=> filled($v)));
            $data['languages'] = $langs ?: null;
        }

        $user->update(Arr::except($data, ['avatar', 'avatar_cropped']));


        return back()->with('ok', __('Profile Updated Successfully.'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => ['required','current_password'],
            'password'              => ['required','min:8','confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('ok', __('Password Updated.'));
    }
}
