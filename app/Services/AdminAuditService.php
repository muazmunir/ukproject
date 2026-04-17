<?php

namespace App\Services;

use App\Models\AdminActionLog;
use Illuminate\Http\Request;

class AdminAuditService
{
    public function log(string $action, ?int $targetId = null, ?string $targetType = null, array $meta = [], ?int $adminUserId = null): AdminActionLog
    {
        $adminUserId = $adminUserId ?? auth()->id();

        $req = request();
        return AdminActionLog::create([
            'admin_user_id' => $adminUserId,
            'action'        => $action,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'meta'          => $meta ?: null,
            'ip'            => $req?->ip(),
            'user_agent'    => substr((string)$req?->userAgent(), 0, 2000),
        ]);
    }
}
