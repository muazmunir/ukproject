<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Illuminate\Http\Request;

class SuperCategoryController extends AdminCategoryController
{
    public function index(Request $r)
    {
        $q   = trim((string)$r->input('q'));
        $per = (int) $r->input('per', 10);
        if (!in_array($per, [10,20,50,100], true)) $per = 10;

        $page = \App\Models\ServiceCategory::query()
            ->when($q, fn($x) => $x->where(function($w) use ($q) {
                $w->where('name','LIKE',"%{$q}%")
                  ->orWhere('slug','LIKE',"%{$q}%");
            }))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate($per)
            ->withQueryString();

        // ✅ SUPERADMIN view
        return view('superadmin.categories.index', [
            'cats' => $page,
            'q'    => $q,
        ]);
    }
}
