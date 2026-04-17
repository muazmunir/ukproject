<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use App\Models\StaffInvite;
use App\Models\StaffDeletionAudit;
use App\Models\StaffDocument;
use App\Mail\StaffInviteMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class StaffController extends Controller
{
    private function staffBase()
{
    return Users::query()
        ->withTrashed()
        ->whereIn('role', ['admin', 'manager'])
        ->with('latestStaffInvite')
        ->withCount('webauthnCredentials');
}

    public function index(Request $r)
    {
        $q    = trim((string) $r->input('q'));
        $role = (string) $r->input('role', 'all'); // all|admin|manager
        $per  = (int) $r->input('per', 10);
        if (!in_array($per, [10,20,50,100], true)) $per = 10;

        $query = $this->staffBase();

        if (in_array($role, ['admin','manager'], true)) {
            $query->where('role', $role);
        }

        $staff = $query
            ->when($q, function($x) use ($q) {
                $x->where(function($w) use ($q) {
                    $w->where('first_name','like',"%{$q}%")
                      ->orWhere('last_name','like',"%{$q}%")
                      ->orWhere('email','like',"%{$q}%")
                      ->orWhere('username','like',"%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($per)
            ->appends(['q'=>$q,'role'=>$role,'per'=>$per]);

        // ✅ counts should include trashed so tabs match list
        $base = Users::query()->withTrashed()->whereIn('role', ['admin','manager']);

        $counts = [
            'all'     => (clone $base)->count(),
            'admin'   => (clone $base)->where('role','admin')->count(),
            'manager' => (clone $base)->where('role','manager')->count(),
        ];

        return view('superadmin.staff.index', compact('staff','q','role','per','counts'));
    }

   public function store(Request $r)
{
    $data = $r->validate([
        'first_name' => ['required','string','max:255'],
        'last_name'  => ['required','string','max:255'],
        'email'      => ['required','email','max:255', Rule::unique('users','email')],
        'role'       => ['required', Rule::in(['admin','manager'])],

        'phone'      => ['required','string','max:30'],
       
        'username'   => ['required','string','max:60', Rule::unique('users', 'username')],

        'profile_pic' => ['nullable','file','mimes:jpg,jpeg,png,webp','max:5120'],

        'emergency_contact_name'  => ['required','string','max:120'],
        'emergency_contact_phone' => ['required','string','max:40'],

        'next_of_kin_name'  => ['required','string','max:120'],
        'next_of_kin_phone' => ['required','string','max:40'],
        'next_of_kin_address' => ['nullable','string','max:255'],

        'government_id_docs'   => ['nullable','array'],
        'government_id_docs.*' => ['file','mimes:jpg,jpeg,png,webp,pdf','max:51200'],

        'additional_label'   => ['nullable','array'],
'additional_label.*' => ['nullable','string','max:160'],

'additional_value'   => ['nullable','array'],
'additional_value.*' => ['nullable','string','max:2000'],


        'additional_docs'   => ['nullable','array'],
        'additional_docs.*' => ['file','mimes:jpg,jpeg,png,webp,pdf','max:51200'],

        'shift_enabled' => ['nullable','in:0,1'],
'shift_start' => ['nullable','date_format:H:i'],
'shift_end' => ['nullable','date_format:H:i'],
'shift_days' => ['nullable','array'],
'shift_days.*' => ['integer','min:1','max:7'],
'started_at' => ['nullable','date'],

    ]);

    $invite = DB::transaction(function () use ($data, $r) {

        // profile pic -> avatar_path (your existing column)
        $avatarPath = null;
        if ($r->hasFile('profile_pic')) {
            $avatarPath = $r->file('profile_pic')->store('staff/avatars', 'public');
        }
     $shiftEnabled = $r->boolean('shift_enabled');

$shiftDays = $r->input('shift_days', [1,2,3,4,5]);
if (!is_array($shiftDays)) $shiftDays = [1,2,3,4,5];
$shiftDays = array_values(array_unique(array_map('intval', $shiftDays)));
sort($shiftDays);

        $user = Users::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => strtolower($data['email']),
            'role'       => $data['role'],
            
          
            'created_by' => auth()->id(),
            'password'   => Hash::make(Str::random(48)),
            'phone'      => $data['phone'],

             'username'   => $data['username'],
           
           
            'avatar_path' => $avatarPath,

            'emergency_contact_name'  => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
            'next_of_kin_name'        => $data['next_of_kin_name'] ?? null,
            'next_of_kin_phone'       => $data['next_of_kin_phone'] ?? null,
            'next_of_kin_address'     => $data['next_of_kin_address'] ?? null,

            'shift_enabled'       => $shiftEnabled,
  'shift_start'         => $shiftEnabled ? ($r->input('shift_start') ?: null) : null,
  'shift_end'           => $shiftEnabled ? ($r->input('shift_end') ?: null) : null,
  'shift_days'          => $shiftEnabled ? $shiftDays : [],
  'started_at' => $data['started_at'] ?? now(),
            
        ]);

        // Government ID docs
        foreach (($r->file('government_id_docs', []) ?? []) as $f) {
            $path = $f->store('staff/docs/government_id', 'public');

            StaffDocument::create([
                'user_id' => $user->id,
                'category' => 'government_id',
                'label' => 'Government ID',
                'file_path' => $path,
                'file_original_name' => $f->getClientOriginalName(),
                'file_size' => $f->getSize(),
                'file_mime' => $f->getMimeType(),
            ]);
        }

        // Additional requirement text (e.g UK NI Number)
       // Additional requirements text (multiple)
$labels = $r->input('additional_label', []);
$values = $r->input('additional_value', []);

if (!is_array($labels)) $labels = [];
if (!is_array($values)) $values = [];

foreach ($labels as $i => $lbl) {
    $lbl = trim((string)$lbl);
    $val = trim((string)($values[$i] ?? ''));

    if ($lbl === '' && $val === '') continue;

    StaffDocument::create([
        'user_id'    => $user->id,
        'category'   => 'additional',
        'label'      => $lbl !== '' ? $lbl : 'Additional Requirement',
        'value_text' => $val !== '' ? $val : null,
    ]);
}

// Additional docs files (not tied to a single requirement label)
foreach (($r->file('additional_docs', []) ?? []) as $f) {
    $path = $f->store('staff/docs/additional', 'public');

    StaffDocument::create([
        'user_id'             => $user->id,
        'category'            => 'additional',
        'label'               => 'Additional Document',
        'file_path'           => $path,
        'file_original_name'  => $f->getClientOriginalName(),
        'file_size'           => $f->getSize(),
        'file_mime'           => $f->getMimeType(),
    ]);
}

        // keep invite flow EXACTLY as your current logic
        StaffInvite::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        return StaffInvite::create([
            'user_id'    => $user->id,
            'token'      => Str::random(64),
            'expires_at' => now()->addHours(24),
            'used_at'    => null,
        ]);
    });

    Mail::to($invite->user->email)->send(new StaffInviteMail($invite));

    return back()->with('ok', 'Staff Created. Invite Email Sent.');
}


    public function resendInvite(Users $user)
    {
        abort_unless(in_array($user->role, ['admin','manager'], true), 404);

        if ((int)($user->is_active ?? 1) !== 1) {
            return back()->with('error', 'This Staff Account Is Disabled.');
        }

        $latest = StaffInvite::where('user_id', $user->id)->latest('id')->first();
        if ($latest && $latest->created_at && $latest->created_at->gt(now()->subSeconds(60))) {
            return back()->with('error', 'Please Wait a Moment Before Resending Another Invite.');
        }

        $invite = DB::transaction(function () use ($user) {
            StaffInvite::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return StaffInvite::create([
                'user_id'    => $user->id,
                'token'      => Str::random(64),
                'expires_at' => now()->addHours(24),
                'used_at'    => null,
            ]);
        });

        Mail::to($user->email)->send(new StaffInviteMail($invite));

        return back()->with('ok', 'Invite Resent.');
    }

  public function update(Request $r, Users $user)
{
    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

    // optional safety: do not edit deleted users
    if ($user->trashed()) {
        return back()->with('error', 'This staff is deleted. Restore first.');
    }

    // optional safety: superadmin cannot edit self in staff list
    if (auth()->id() === $user->id) {
        return back()->with('error', 'You Cannot Edit Your Own Account Here.');
    }

   $data = $r->validate([
  'first_name' => ['required','string','max:60'],
  'last_name'  => ['nullable','string','max:60'],
  'role'       => ['required', Rule::in(['admin','manager'])],
  'phone'      => ['required','string','max:30'],
  'username'   => [
      'required',
      'string',
      'max:60',
      Rule::unique('users', 'username')->ignore($user->id)
  ],

  'emergency_contact_name'  => ['required','string','max:120'],
  'emergency_contact_phone' => ['required','string','max:40'],
  'next_of_kin_name'        => ['required','string','max:120'],
  'next_of_kin_phone'       => ['required','string','max:40'],
  'next_of_kin_address'     => ['nullable','string','max:255'],

  'profile_pic'   => ['nullable','file','mimes:jpg,jpeg,png,webp','max:5120'],
  'remove_avatar' => ['nullable','in:0,1'],

  // ✅ NEW: allow adding docs on edit
  'government_id_docs'   => ['nullable','array'],
  'government_id_docs.*' => ['file','mimes:jpg,jpeg,png,webp,pdf','max:51200'],

  'additional_label'   => ['nullable','array'],
  'additional_label.*' => ['nullable','string','max:160'],
  'additional_value'   => ['nullable','array'],
  'additional_value.*' => ['nullable','string','max:2000'],

  'additional_docs'   => ['nullable','array'],
  'additional_docs.*' => ['file','mimes:jpg,jpeg,png,webp,pdf','max:51200'],
  'shift_enabled' => ['nullable','in:0,1'],
'shift_start' => ['nullable','date_format:H:i'],
'shift_end' => ['nullable','date_format:H:i'],
'shift_days' => ['nullable','array'],
'shift_days.*' => ['integer','min:1','max:7'],


  
]);


    DB::transaction(function () use ($r, $user, $data) {

        // ✅ active flag from checkbox
        

        // ✅ avatar remove/replace
        $removeAvatar = $r->input('remove_avatar') === '1';

        // remove current avatar if requested and no new upload
        if ($removeAvatar && !$r->hasFile('profile_pic')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = null;
        }

        // replace avatar if new uploaded
        if ($r->hasFile('profile_pic')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $r->file('profile_pic')->store('staff/avatars', 'public');
            $user->avatar_path = $path;
        }

        // ✅ update fields
        $user->first_name = $data['first_name'];
        $user->last_name  = $data['last_name'] ?? null;
        $user->role       = $data['role'];

        $user->phone      = $data['phone'];

        $user->username   = $data['username'];

        $user->emergency_contact_name  = $data['emergency_contact_name'];
        $user->emergency_contact_phone = $data['emergency_contact_phone'];
        $user->next_of_kin_name        = $data['next_of_kin_name'] ?? null;
        $user->next_of_kin_phone       = $data['next_of_kin_phone'] ?? null;
        $user->next_of_kin_address     = $data['next_of_kin_address'] ?? null;
      $shiftEnabled = $r->boolean('shift_enabled');

$shiftDays = $r->input('shift_days', [1,2,3,4,5]);
if (!is_array($shiftDays)) $shiftDays = [1,2,3,4,5];
$shiftDays = array_values(array_unique(array_map('intval', $shiftDays)));
sort($shiftDays);

$user->shift_enabled = $shiftEnabled;
$user->shift_start   = $shiftEnabled ? ($r->input('shift_start') ?: null) : null;
$user->shift_end     = $shiftEnabled ? ($r->input('shift_end') ?: null) : null;
$user->shift_days    = $shiftEnabled ? $shiftDays : null;



        

        $user->save();
        // ✅ add more government docs (optional)
foreach (($r->file('government_id_docs', []) ?? []) as $f) {
  $path = $f->store('staff/docs/government_id', 'public');

  StaffDocument::create([
    'user_id' => $user->id,
    'category' => 'government_id',
    'label' => 'Government ID',
    'file_path' => $path,
    'file_original_name' => $f->getClientOriginalName(),
    'file_size' => $f->getSize(),
    'file_mime' => $f->getMimeType(),
  ]);
}

// ✅ add more additional requirements (text)
$labels = $r->input('additional_label', []);
$values = $r->input('additional_value', []);
if (!is_array($labels)) $labels = [];
if (!is_array($values)) $values = [];

foreach ($labels as $i => $lbl) {
  $lbl = trim((string)$lbl);
  $val = trim((string)($values[$i] ?? ''));

  if ($lbl === '' && $val === '') continue;

  StaffDocument::create([
    'user_id'    => $user->id,
    'category'   => 'additional',
    'label'      => $lbl !== '' ? $lbl : 'Additional Requirement',
    'value_text' => $val !== '' ? $val : null,
  ]);
}

// ✅ add more additional docs files
foreach (($r->file('additional_docs', []) ?? []) as $f) {
  $path = $f->store('staff/docs/additional', 'public');

  StaffDocument::create([
    'user_id'            => $user->id,
    'category'           => 'additional',
    'label'              => 'Additional Document',
    'file_path'          => $path,
    'file_original_name' => $f->getClientOriginalName(),
    'file_size'          => $f->getSize(),
    'file_mime'          => $f->getMimeType(),
  ]);
}

    });

    return back()->with('ok', 'Staff Updated.');
}


   
    public function destroy(Request $r, $id)
    {
        $user = Users::withTrashed()->findOrFail($id);
    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You Cannot Delete Your Own Account.');
        }

        if ($user->trashed()) {
            return back()->with('error', 'Already deleted.');
        }

        $data = $r->validate([
            'reason' => ['required','string'],  // unlimited
            'images' => ['nullable','array'],
            'images.*' => ['file','mimes:jpg,jpeg,png,webp','max:51200'], // 50MB per file
        ]);

        DB::transaction(function () use ($user, $data, $r) {

            $performedBy = auth()->id();
            $files = $r->file('images', []);

            if (empty($files)) {
                StaffDeletionAudit::create([
                    'user_id'      => $user->id,
                    'performed_by' => $performedBy,
                    'reason'       => $data['reason'],
                    'image_path'   => null,
                    'ip'           => $r->ip(),
                    'user_agent'   => substr((string)$r->userAgent(), 0, 1000),
                ]);
            } else {
                foreach ($files as $f) {
                    $path = $f->store('staff/audits/deletions', 'public');

                    StaffDeletionAudit::create([
                        'user_id'             => $user->id,
                        'performed_by'        => $performedBy,
                        'reason'              => $data['reason'],
                        'image_path'          => $path,
                        'image_original_name' => $f->getClientOriginalName(),
                        'image_size'          => $f->getSize(),
                        'image_mime'          => $f->getMimeType(),
                        'ip'                  => $r->ip(),
                        'user_agent'          => substr((string)$r->userAgent(), 0, 1000),
                    ]);
                }
            }

            $user->delete();
        });

        return back()->with('ok', 'Staff Soft Deleted (Audit Saved).');
    }

   public function restore($id)
{
    $user = Users::withTrashed()->findOrFail($id);
    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

    if (! $user->trashed()) return back()->with('error', 'This staff is not deleted.');
    $user->restore();

    return back()->with('ok', 'Staff Restored.');
}

    // ✅ JSON endpoint for audit modal
    public function audit($id)
    {

       $user = Users::withTrashed()->findOrFail($id);
    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

        $audits = StaffDeletionAudit::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $latest = $audits->first();

        $performedByName = null;
        if ($latest && $latest->performed_by) {
            $p = Users::withTrashed()->find($latest->performed_by);
            if ($p) {
                $performedByName = trim(($p->first_name ?? '').' '.($p->last_name ?? '')) ?: ($p->email ?? null);
            }
        }

        $images = $audits->whereNotNull('image_path')->values()->map(function($a){
            return [
                'url'  => asset('storage/'.$a->image_path),
                'name' => $a->image_original_name,
                'size' => $a->image_size,
                'mime' => $a->image_mime,
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'deleted_at' => optional($user->deleted_at)->toDateTimeString(),
                'created_at' => optional($user->created_at)->toDateTimeString(),
            ],
            'audit' => [
                'performed_by' => $performedByName,
                'reason' => $latest?->reason,
                'ip' => $latest?->ip,
                'user_agent' => $latest?->user_agent,
                'images' => $images,
            ],
        ]);
    }

   public function info($id)
{
    $user = Users::withTrashed()
        ->with('latestStaffInvite')
        ->findOrFail($id);

    abort_unless(in_array($user->role, ['admin','manager'], true), 404);

    // ✅ staff documents
    $govDocs = StaffDocument::query()
        ->where('user_id', $user->id)
        ->where('category', 'government_id')   // must match DB
        ->whereNotNull('file_path')
        ->orderByDesc('id')
        ->get();

    // one “additional requirement” text row (value_text)
   $additionalRequirements = StaffDocument::query()
    ->where('user_id', $user->id)
    ->where('category', 'additional')
    ->whereNotNull('value_text')
    ->orderByDesc('id')
    ->get();


    $additionalDocs = StaffDocument::query()
        ->where('user_id', $user->id)
        ->where('category', 'additional')      // must match DB
        ->whereNotNull('file_path')
        ->orderByDesc('id')
        ->get();

    // audits (your existing code)
    $audits = StaffDeletionAudit::query()
        ->where('user_id', $user->id)
        ->orderByDesc('id')
        ->get();

    $latest = $audits->first();

    $performedBy = null;
    if ($latest && $latest->performed_by) {
        $p = Users::withTrashed()->find($latest->performed_by);
        $performedBy = $p ? (trim(($p->first_name ?? '').' '.($p->last_name ?? '')) ?: ($p->email ?? null)) : null;
    }

    $images = $audits->whereNotNull('image_path')->values();

    return view('superadmin.staff.info', compact(
        'user','latest','performedBy','images',
       'govDocs','additionalRequirements','additionalDocs'

    ));
}


public function create()
{
  return view('superadmin.staff.create');
}

public function edit(Users $user)
{
  abort_unless(in_array($user->role, ['admin','manager'], true), 404);

  if ($user->trashed()) {
    return redirect()->route('superadmin.staff.index')->with('error','This staff is deleted. Restore first.');
  }

  $govDocs = StaffDocument::query()
    ->where('user_id', $user->id)
    ->where('category', 'government_id')
    ->orderByDesc('id')
    ->get();

  $additionalRequirements = StaffDocument::query()
    ->where('user_id', $user->id)
    ->where('category', 'additional')
    ->whereNotNull('value_text')
    ->orderByDesc('id')
    ->get();

  $additionalDocs = StaffDocument::query()
    ->where('user_id', $user->id)
    ->where('category', 'additional')
    ->whereNotNull('file_path')
    ->orderByDesc('id')
    ->get();

  return view('superadmin.staff.edit', compact('user','govDocs','additionalRequirements','additionalDocs'));
}



public function webauthnIndex($id)
{
    $user = Users::withTrashed()->findOrFail($id);

    abort_unless(in_array($user->role, ['admin', 'manager'], true), 404);

    $credentials = $user->webauthnCredentials()
        ->latest()
        ->get();

    return view('superadmin.staff.webauthn.index', [
        'user' => $user,
        'credentials' => $credentials,
    ]);
}

public function webauthnDestroy($id, $credentialId)
{
    $user = Users::withTrashed()->findOrFail($id);

    abort_unless(in_array($user->role, ['admin', 'manager'], true), 404);

    $credential = $user->webauthnCredentials()
        ->whereKey($credentialId)
        ->firstOrFail();

    $credential->delete();

    return back()->with('ok', 'Passkey removed successfully.');
}

public function webauthnReset($id)
{
    $user = Users::withTrashed()->findOrFail($id);

    abort_unless(in_array($user->role, ['admin', 'manager'], true), 404);

    $user->webauthnCredentials()->delete();

    return back()->with('ok', 'All passkeys have been reset.');
}

}

