<?php
// app/Http/Controllers/Admin/CategoryController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $r)
    {
        $q   = trim((string)$r->input('q'));
        $per = (int) $r->input('per', 10);
        if (!in_array($per, [10,20,50,100], true)) $per = 10;
    
        $page = ServiceCategory::query()
            ->when($q, fn($x) => $x->where(function($w) use ($q) {
                $w->where('name','LIKE',"%{$q}%")
                  ->orWhere('slug','LIKE',"%{$q}%");
            }))
            ->orderBy('sort_order')->orderBy('name')
           ->paginate($per)
->withQueryString(); // automatically keeps ?q= & ?per= etc.
// preserve filters
    
        return view('admin.categories.index', ['cats'=>$page, 'q'=>$q]);
    }
    

    public function store(Request $r)
    {
       $isActive = $r->boolean('is_active'); // safe conversion

$data = $r->validate([
    'name'        => ['required','string','max:120'],
    'description' => ['nullable','string','max:1000'],
    'sort_order'  => ['nullable','integer','min:0'],
    'cover_image' => ['nullable','file','mimes:jpg,jpeg,png,webp','max:2048'],
    'icon_path'   => ['nullable','file','mimes:svg,svgz,jpg,jpeg,png,webp','max:1024'],
    'show_in_scrollbar' => ['nullable'],
]);

// just set active manually
$data['is_active'] = $isActive;


        $slug = Str::slug($data['name']);
        if (ServiceCategory::where('slug',$slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }

        $cover = null;
        $icon  = null;

        if ($r->hasFile('cover_image')) {
            $cover = $r->file('cover_image')->store('categories/covers','public');
        }
        if ($r->hasFile('icon_path')) {
            $icon = $r->file('icon_path')->store('categories/icons','public');
        }

        ServiceCategory::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $r->boolean('is_active'), // default false if unchecked
            'cover_image' => $cover,
            'icon_path'   => $icon,
            'show_in_scrollbar' => $r->boolean('show_in_scrollbar'),

        ]);

        return back()->with('ok','Category Created.');
    }

    public function update(Request $r, ServiceCategory $category)
    {
        $data = $r->validate([
            'name'        => ['required','string','max:120'],
            'description' => ['nullable','string','max:1000'],
            'sort_order'  => ['nullable','integer','min:0'],
            // use boolean() later for checkbox
            'is_active'   => ['nullable'],
        
            // Raster cover: keep image rule
            'cover_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
        
            // Icons: allow SVG OR raster. DO NOT use the 'image' rule here.
            // Option A (simple): use mimes without 'image'
            'icon_path'   => ['nullable','file','mimes:svg,svgz,png,jpg,jpeg,webp','max:1024'],

            'remove_cover' => ['nullable','in:0,1'],
'remove_icon'  => ['nullable','in:0,1'],

            'show_in_scrollbar' => ['nullable'],

            // Option B (stricter mime):
            // 'icon_path' => ['nullable','file','mimetypes:image/svg+xml,image/png,image/jpeg,image/webp','max:1024'],
        ]);

        // Update slug only if name changed
        if ($category->name !== $data['name']) {
            $slug = Str::slug($data['name']);
            if (ServiceCategory::where('slug',$slug)->where('id','!=',$category->id)->exists()) {
                $slug .= '-'.Str::random(4);
            }
            $category->slug = $slug;
        }
        // ✅ Remove existing cover (if requested)
if ($r->input('remove_cover') === '1') {
    if ($category->cover_image) {
        Storage::disk('public')->delete($category->cover_image);
    }
    $category->cover_image = null;
}

// ✅ Remove existing icon (if requested)
if ($r->input('remove_icon') === '1') {
    if ($category->icon_path) {
        Storage::disk('public')->delete($category->icon_path);
    }
    $category->icon_path = null;
}


        if ($r->hasFile('cover_image')) {
            if ($category->cover_image) Storage::disk('public')->delete($category->cover_image);
            $category->cover_image = $r->file('cover_image')->store('categories/covers','public');
        }
        if ($r->hasFile('icon_path')) {
            if ($category->icon_path) Storage::disk('public')->delete($category->icon_path);
            $category->icon_path = $r->file('icon_path')->store('categories/icons','public');
        }

        $category->fill([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $r->boolean('is_active'),
            'show_in_scrollbar' => $r->boolean('show_in_scrollbar'),

        ])->save();

        return back()->with('ok','Category Updated.');
    }

    public function activate(ServiceCategory $category)
    {
        $category->update(['is_active'=>true]);
        return back()->with('ok','Category Activated.');
    }

    public function deactivate(ServiceCategory $category)
    {
        $category->update(['is_active'=>false]);
        return back()->with('ok','Category deactivated.');
    }

    public function destroy(ServiceCategory $category)
    {
        if ($category->cover_image) Storage::disk('public')->delete($category->cover_image);
        if ($category->icon_path)  Storage::disk('public')->delete($category->icon_path);
        $category->delete();
        return back()->with('ok','Category Deleted.');
    }
}
