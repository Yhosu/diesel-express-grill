<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable;
    protected $fillable = [
        'expense_code',
        'expense_date',
        'shift_id',
        'unit_code',
        'location',
        'category',
        'description',
        'supplier',
        'document_ref',
        'amount',
        'payment_method',
        'responsible',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}

