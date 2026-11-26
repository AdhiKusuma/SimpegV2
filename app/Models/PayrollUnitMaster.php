<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollUnitMaster extends Model
{
    protected $table = 'payroll_unit_masters';

    protected $fillable = ['lookup_key', 'name', 'office_code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollAssignment::class, 'payroll_unit_master_id');
    }
}
