<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use Illuminate\Support\Facades\Storage;

class StaffDocumentController extends Controller
{
  public function destroy(StaffDocument $doc)
  {
    // basic safety: only allow deleting staff docs
    if (!in_array($doc->category, ['government_id','additional'], true)) {
      return back()->with('error', 'Invalid document.');
    }

    // delete file if present
    if ($doc->file_path) {
      Storage::disk('public')->delete($doc->file_path);
    }

    $doc->delete();

    return back()->with('ok', 'Document deleted.');
  }
}
