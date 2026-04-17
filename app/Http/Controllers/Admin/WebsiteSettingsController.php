<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceFee;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteSettingsController extends Controller
{
    /* ===== Utils ===== */

    private function coachFee(): ServiceFee
    {
        return ServiceFee::firstOrCreate(
            ['slug' => 'coach_commission'],
            [
                'label'    => 'Coach commission rate',
                'party'    => 'coach',
                'type'     => 'percent',
                'amount'   => 0,
                'is_active'=> true,
            ]
        );
    }

    private function clientFee(): ServiceFee
    {
        return ServiceFee::firstOrCreate(
            ['slug' => 'client_commission'],
            [
                'label'    => 'Customer commission rate',
                'party'    => 'client',
                'type'     => 'percent',
                'amount'   => 0,
                'is_active'=> true,
            ]
        );
    }

    private function setting(string $key, $default = null)
    {
        return SiteSetting::getValue($key, $default);
    }

    private function setSetting(string $key, $value): void
    {
        SiteSetting::setValue($key, $value);
    }

    /* ===== Trainer settings (coach commission etc.) ===== */

    public function trainerEdit()
    {
        $coachFee  = $this->coachFee();
        $showSocial = (bool) $this->setting('trainer_show_social_profiles', true);
        $defaultCover = $this->setting('trainer_default_cover', null);

        return view('admin.website.trainer', compact(
            'coachFee',
            'showSocial',
            'defaultCover'
        ));
    }

    public function trainerUpdate(Request $r)
    {
        $data = $r->validate([
            'commission'   => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1})?$/'],

            'show_social'  => ['nullable', 'boolean'],
            'default_cover'=> ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_default_cover' => ['nullable', 'boolean'],
        ]);

        // update fee
        $coachFee = $this->coachFee();
        $coachFee->update([
            'amount' => min(100, max(0, round((float)$data['commission'], 1))),

            'type'   => 'percent',
            'party'  => 'coach',
        ]);

        // show social
        $this->setSetting('trainer_show_social_profiles', $r->boolean('show_social') ? '1' : '0');


        if ($r->boolean('remove_default_cover')) {
    $this->deleteSettingImage('trainer_default_cover');
}
        // default cover image
      if ($r->hasFile('default_cover')) {
    $old = $this->setting('trainer_default_cover');
    if ($old) Storage::disk('public')->delete($old);

    $path = $r->file('default_cover')->store('settings/trainers', 'public');
    $this->setSetting('trainer_default_cover', $path);
}

        return back()->with('ok', 'Trainer Settings Updated.');
    }

    /* ===== Customer settings (client commission) ===== */

    public function customerEdit()
    {
        $clientFee = $this->clientFee();

        return view('admin.website.customer', compact('clientFee'));
    }

    public function customerUpdate(Request $r)
    {
        $data = $r->validate([
            'commission' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1})?$/'],

        ]);

        $clientFee = $this->clientFee();
        $clientFee->update([
            'amount' => min(100, max(0, round((float)$data['commission'], 1))),

            'type'   => 'percent',
            'party'  => 'client',
        ]);

        return back()->with('ok', 'Customer Settings Updated.');
    }

    /* ===== Site customization (hero images etc.) ===== */

    public function appearanceEdit()
    {
        $searchBg  = $this->setting('homepage_search_bg');
        $middleImg = $this->setting('homepage_middle_banner');

        return view('admin.website.appearance', compact('searchBg', 'middleImg'));
    }

    public function appearanceUpdate(Request $r)
    {
        $data = $r->validate([
            'homepage_search_bg'  => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
            'homepage_middle_img' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
              'remove_homepage_search_bg' => ['nullable','boolean'],
    'remove_homepage_middle_banner' => ['nullable','boolean'],
        ]);


        if ($r->boolean('remove_homepage_search_bg')) {
    $this->deleteSettingImage('homepage_search_bg');
}

if ($r->boolean('remove_homepage_middle_banner')) {
    $this->deleteSettingImage('homepage_middle_banner');
}


        // search background
        if ($r->hasFile('homepage_search_bg')) {
            $old = $this->setting('homepage_search_bg');
            if ($old) Storage::disk('public')->delete($old);

            $path = $r->file('homepage_search_bg')->store('settings/homepage', 'public');
            $this->setSetting('homepage_search_bg', $path);
        }

        // middle banner
        if ($r->hasFile('homepage_middle_img')) {
            $old = $this->setting('homepage_middle_banner');
            if ($old) Storage::disk('public')->delete($old);

            $path = $r->file('homepage_middle_img')->store('settings/homepage', 'public');
            $this->setSetting('homepage_middle_banner', $path);
        }

        return back()->with('ok', 'Website Content Updated.');
    }


    private function deleteSettingImage(string $key): bool
{
    $old = $this->setting($key);
    if (!$old) return true;

    // Safety: delete only inside these folders
    $allowedPrefixes = [
        'settings/trainers/',
        'settings/homepage/',
    ];

    $okPrefix = false;
    foreach ($allowedPrefixes as $prefix) {
        if (Str::startsWith($old, $prefix)) { $okPrefix = true; break; }
    }

    if (!$okPrefix) {
        // Don't delete unexpected paths
        $this->setSetting($key, null);
        return false;
    }

    Storage::disk('public')->delete($old);
    $this->setSetting($key, null);

    return true;
}

public function trainerDefaultCoverDelete(Request $r)
{
    $this->deleteSettingImage('trainer_default_cover');
    return back()->with('ok', 'Default cover removed.');
}

public function appearanceSearchBgDelete(Request $r)
{
    $this->deleteSettingImage('homepage_search_bg');
    return back()->with('ok', 'Search background removed.');
}

public function appearanceMiddleBannerDelete(Request $r)
{
    $this->deleteSettingImage('homepage_middle_banner');
    return back()->with('ok', 'Middle banner removed.');
}
}
