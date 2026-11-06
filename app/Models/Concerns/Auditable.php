<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\OperationalLocation;
use App\Models\Product;
use App\Models\ProductBom;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftException;
use App\Models\ShiftTemplate;
use App\Models\Supply;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            self::writeAuditLog($model, 'created');
        });

        static::updated(function (Model $model): void {
            self::writeAuditLog($model, 'updated');
        });

        static::deleted(function (Model $model): void {
            self::writeAuditLog($model, 'deleted');
        });
    }

    protected static function writeAuditLog(Model $model, string $event): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            $ignoredKeys = ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password'];

            $oldValues = null;
            $newValues = null;

            if ($event === 'created') {
                $newValues = self::normalizeAuditValues(
                    array_diff_key($model->getAttributes(), array_flip($ignoredKeys))
                );
            } elseif ($event === 'updated') {
                $changes = array_diff_key($model->getChanges(), array_flip($ignoredKeys));
                if ($changes === []) {
                    return;
                }

                $original = [];
                foreach (array_keys($changes) as $key) {
                    $original[$key] = $model->getOriginal($key);
                }

                $oldValues = self::normalizeAuditValues($original);
                $newValues = self::normalizeAuditValues($changes);
            } elseif ($event === 'deleted') {
                $oldValues = self::normalizeAuditValues(
                    array_diff_key($model->getOriginal(), array_flip($ignoredKeys))
                );
            }

            /** @var User|null $user */
            $user = Auth::user();
            $userRole = 'Sistema / Automático';
            if ($user instanceof User) {
                $roleEnum = $user->role instanceof UserRole
                    ? $user->role
                    : UserRole::tryFrom((string) $user->role);
                $userRole = $roleEnum?->getLabel() ?? (string) $user->role;
            }

            $module = self::resolveAuditModule($model);
            $recordLabel = self::resolveAuditRecordLabel($model);
            $summary = self::buildAuditSummary($event, $module, $recordLabel, $oldValues, $newValues);

            $request = app()->bound('request') ? request() : null;

            AuditLog::query()->create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'user_role' => $userRole,
                'event' => $event,
                'module' => $module,
                'auditable_type' => $model::class,
                'auditable_id' => (int) $model->getKey(),
                'record_label' => $recordLabel,
                'summary' => $summary,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request?->ip() ?? '127.0.0.1',
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 250) : 'CLI / Sistema',
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // No interrumpir flujo operativo si ocurre error en auditoría durante migración inicial
        }
    }

    protected static function normalizeAuditValues(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $val) {
            if ($val instanceof BackedEnum) {
                $normalized[$key] = $val->value;
            } elseif (is_object($val) && method_exists($val, 'format')) {
                $normalized[$key] = $val->format('Y-m-d H:i:s');
            } else {
                $normalized[$key] = $val;
            }
        }

        return $normalized;
    }

    protected static function resolveAuditModule(Model $model): string
    {
        return match ($model::class) {
            Shift::class => 'Turno Diario',
            Sale::class => 'Venta POS',
            ShiftException::class => 'Merma / Excepción',
            Product::class => 'Producto',
            ProductBom::class => 'Receta BOM',
            Supply::class => 'Insumo / Empaque',
            Purchase::class => 'Compra de Insumo',
            Expense::class => 'Egreso Operativo',
            ShiftTemplate::class => 'Configuración de Turno',
            OperationalLocation::class => 'Ubicación Operativa',
            User::class => 'Usuario y Rol',
            default => class_basename($model),
        };
    }

    protected static function resolveAuditRecordLabel(Model $model): string
    {
        return match (true) {
            $model instanceof Shift => (string) ($model->shift_code ?: "Turno #{$model->getKey()}"),
            $model instanceof Sale => (string) ($model->transaction_code ?: "Venta #{$model->getKey()}"),
            $model instanceof ShiftException => (string) ($model->exception_code ?: "Excepción #{$model->getKey()}"),
            $model instanceof Product => trim(($model->sku ?? '') . ' - ' . ($model->name ?? '')),
            $model instanceof Supply => trim(($model->code ?? '') . ' - ' . ($model->name ?? '')),
            $model instanceof Purchase => (string) ($model->purchase_code ?: "Compra #{$model->getKey()}"),
            $model instanceof Expense => (string) ($model->expense_code ?: "Egreso #{$model->getKey()}"),
            $model instanceof ShiftTemplate => trim(($model->code ?? '') . ' - ' . ($model->name ?? '')),
            $model instanceof OperationalLocation => trim(($model->code ?? '') . ' - ' . ($model->name ?? '')),
            $model instanceof User => trim(($model->name ?? '') . ' (' . ($model->email ?? '') . ')'),
            default => '#' . (string) $model->getKey(),
        };
    }

    protected static function buildAuditSummary(
        string $event,
        string $module,
        string $recordLabel,
        ?array $oldValues,
        ?array $newValues
    ): string {
        if ($event === 'created') {
            return "Registro inicial de {$module} [{$recordLabel}].";
        }

        if ($event === 'deleted') {
            return "Eliminación de {$module} [{$recordLabel}].";
        }

        if ($event === 'updated' && is_array($newValues)) {
            $parts = [];
            foreach ($newValues as $field => $newVal) {
                $oldVal = $oldValues[$field] ?? 'vacío';
                $oldStr = is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? 'null') : json_encode($oldVal);
                $newStr = is_scalar($newVal) || $newVal === null ? (string) ($newVal ?? 'null') : json_encode($newVal);
                $parts[] = "{$field}: '{$oldStr}' → '{$newStr}'";
            }

            $detail = implode(', ', array_slice($parts, 0, 5));
            if (count($parts) > 5) {
                $detail .= ' (+' . (count($parts) - 5) . ' campos)';
            }

            return "Modificación en {$module} [{$recordLabel}]: {$detail}";
        }

        return "Cambio en {$module} [{$recordLabel}].";
    }
}

