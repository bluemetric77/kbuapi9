<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class VariableCost extends Model
{
    protected $table = 'm_variable_cost';
    protected $primaryKey = 'line_no';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_active'=>'string',
        'standat_cost'=>'float',
        'is_editable'=>'string',
        'cost'=>'float'
    ];
}
