<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Campos sensíveis que nunca devem ser gravados em texto claro nos logs de auditoria.
     */
    protected static array $sensitiveAuditFields = [
        'password',
        'remember_token',
        'encrypted_p12',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'secret',
        'api_key',
    ];

    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logAudit('create', null, $model->sanitizeForAudit($model->getAttributes()));
        });

        static::updated(function ($model) {
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
                $model->logAudit('update', $model->sanitizeForAudit($old), $model->sanitizeForAudit($new));
            }
        });

        static::deleted(function ($model) {
            $model->logAudit('delete', $model->sanitizeForAudit($model->getAttributes()), null);
        });
    }

    public function logAudit($action, $oldValues = null, $newValues = null)
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => is_array($oldValues) ? $this->sanitizeForAudit($oldValues) : $oldValues,
            'new_values' => is_array($newValues) ? $this->sanitizeForAudit($newValues) : $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Remove ou redige campos sensíveis antes de salvar no AuditLog.
     */
    public function sanitizeForAudit(?array $attributes): ?array
    {
        if ($attributes === null) {
            return null;
        }

        $sanitized = $attributes;
        foreach (static::$sensitiveAuditFields as $sensitive) {
            if (array_key_exists($sensitive, $sanitized)) {
                $sanitized[$sensitive] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    public function audits()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
