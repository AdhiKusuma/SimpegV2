<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollGradeMaster extends Model
{
    protected $table = 'payroll_grade_masters';

    protected $fillable = ['code', 'label', 'is_active', 'source'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollAssignment::class, 'payroll_grade_master_id');
    }

    public function legacyAssignments(): HasMany
    {
        return $this->hasMany(EmployeePayrollGrade::class, 'payroll_grade_master_id');
    }
}
