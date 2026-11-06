<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationSemaphore;
use App\Enums\ShiftStatus;
use App\Models\Concerns\Auditable;
use App\Services\ShiftReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use Auditable;

    protected $fillable = [
        'shift_code',
        'shift_template_id',
        'operational_location_id',
        'shift_date',
        'unit_code',
        'location',
        'operator_name',
        'start_time',
        'close_time',
        'starting_float',
        'operator_accepted_custody',
        'counted_cash',
        'confirmed_qr',
        'sales_potential',
        'expected_cash_sales',
        'expected_qr_sales',
        'actual_cash_sales',
        'total_expected_sales',
        'total_actual_sales',
        'cash_variance',
        'qr_variance',
        'total_financial_variance',
        'cash_semaphore',
        'qr_semaphore',
        'total_semaphore',
        'total_dispatched_qty',
        'total_sold_qty',
        'total_waste_qty',
        'total_comp_qty',
        'total_staff_qty',
        'total_closing_qty',
        'total_inventory_variance_qty',
        'inventory_variance_value',
        'waste_pct',
        'total_cogs',
        'gross_contribution',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'starting_float' => 'decimal:2',
            'operator_accepted_custody' => 'boolean',
            'counted_cash' => 'decimal:2',
            'confirmed_qr' => 'decimal:2',
            'sales_potential' => 'decimal:2',
            'expected_cash_sales' => 'decimal:2',
            'expected_qr_sales' => 'decimal:2',
            'actual_cash_sales' => 'decimal:2',
            'total_expected_sales' => 'decimal:2',
            'total_actual_sales' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'qr_variance' => 'decimal:2',
            'total_financial_variance' => 'decimal:2',
            'cash_semaphore' => ReconciliationSemaphore::class,
            'qr_semaphore' => ReconciliationSemaphore::class,
            'total_semaphore' => ReconciliationSemaphore::class,
            'inventory_variance_value' => 'decimal:2',
            'waste_pct' => 'decimal:6',
            'total_cogs' => 'decimal:2',
            'gross_contribution' => 'decimal:2',
            'status' => ShiftStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift): void {
            if ($shift->shift_template_id) {
                $template = ShiftTemplate::query()->find($shift->shift_template_id);
                if ($template) {
                    $shift->start_time = $shift->start_time ?: $template->start_time;
                    $shift->close_time = $shift->close_time ?: $template->end_time;
                    if ((float) $shift->starting_float <= 0) {
                        $shift->starting_float = $template->default_float;
                    }
                }
            }

            if ($shift->operational_location_id) {
                $opLoc = OperationalLocation::query()->find($shift->operational_location_id);
                if ($opLoc) {
                    $shift->location = $opLoc->name;
                    $shift->unit_code = $shift->unit_code ?: $opLoc->default_unit_code;
                }
            } elseif (! empty($shift->location)) {
                $opLoc = OperationalLocation::query()->where('name', $shift->location)->first();
                if ($opLoc) {
                    $shift->operational_location_id = $opLoc->id;
                }
            }
        });

        static::saved(function (Shift $shift): void {
            ShiftReconciliationService::reconcile($shift);
        });
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function operationalLocation(): BelongsTo
    {
        return $this->belongsTo(OperationalLocation::class, 'operational_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShiftItem::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShiftException::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

