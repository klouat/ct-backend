<?php

namespace App\Support;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(
        ?int $userId,
        string $action,
        string $tableName,
        ?int $recordId = null,
        ?string $description = null
    ): void {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
