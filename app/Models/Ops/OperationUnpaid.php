<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class OperationUnpaid extends Model
{
    protected $table = 't_ar_document';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'paid' => 'float',
        'unpaid'=>'float',
        'is_void'=>'string',
        'is_paid'=>'string'

    ];
}
