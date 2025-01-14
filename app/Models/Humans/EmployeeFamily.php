<?php

namespace App\Models\Humans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class EmployeeFamily extends Model
{
    protected $table = 'm_employee_family';
    protected $primaryKey = 'line_id';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_transfer' => 'string',
        'is_active' => 'string',
    ];
}
