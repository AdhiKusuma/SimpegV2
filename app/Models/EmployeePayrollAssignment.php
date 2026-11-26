<?php

namespace App\Models;

use App\Models\Legacy\TbPegawai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollAssignment extends Model
{
    protected $table = 'employee_payroll_assignments';

    protected $fillable = [
        'employee_id',
        'payroll_grade_master_id',
        'payroll_unit_master_id',
        'masa_kerja',
        'effective_from',
        'effective_to',
        'source_filename',
        'source_row_number',
    ];

    protected $casts = [
        'masa_kerja' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'source_row_number' => 'integer',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(PayrollGradeMaster::class, 'payroll_grade_master_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PayrollUnitMaster::class, 'payroll_unit_master_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TbPegawai::class, 'employee_id', 'id_peg');
    }
}
