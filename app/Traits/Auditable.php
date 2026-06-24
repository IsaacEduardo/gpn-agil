<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logAudit('create', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            // Filter out ignored fields if defined in model: protected $auditIgnore = ['updated_at'];
            $old = $model->getOriginal();
            $new = $model->getAttributes();

            // Basic filtering of unchanged fields
            $changes = [];
            foreach ($new as $key => $value) {
                if (array_key_exists($key, $old) && $old[$key] !== $value) {
                    $changes[$key] = $value;
                }
            }

            if (! empty($changes)) {
                $model->logAudit('update', $old, $new);
            }
        });

        static::deleted(function ($model) {
            $model->logAudit('delete', $model->getAttributes(), null);
        });
    }

    public function logAudit($action, $oldValues = null, $newValues = null)
    {
        // Don't log if running in console (unless specified) or no user (system action)
        // But for EDMS, system actions should also be logged (user_id = null)

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function audits()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
