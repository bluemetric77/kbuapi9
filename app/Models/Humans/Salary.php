<?php

namespace App\Models\Humans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Salary extends Model
{
    protected $table = 'm_salary';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
}
