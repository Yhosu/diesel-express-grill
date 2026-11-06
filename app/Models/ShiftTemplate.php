<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    use Auditable;

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'default_float',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_float' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'shift_template_id');
    }
}

