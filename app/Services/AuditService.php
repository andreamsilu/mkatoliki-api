<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Arr;

final class AuditService
{
    public function record(?User $actor, string $action, string $type, int $id, array $old = [], array $new = []): AuditLog
    {
        $sensitive = ['password', 'remember_token', 'token', 'first_name', 'middle_name', 'last_name', 'family_name', 'address', 'phone', 'email', 'date_of_birth', 'gender', 'rows', 'notes'];
        $redact = static function (array $values) use ($sensitive): array {
            foreach (array_intersect(array_keys($values), $sensitive) as $field) {
                $values[$field] = '[REDACTED]';
            }

            return Arr::except($values, ['created_at', 'updated_at']);
        };

        return AuditLog::create([
            'user_id' => $actor?->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_values' => $redact($old), 'new_values' => $redact($new),
            'ip_address' => request()->ip(), 'user_agent' => substr(request()->userAgent() ?? '', 0, 512),
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }
}
