<?php

namespace App\Models\Humans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Attendance1 extends Model
{
    protected $table = 't_attendance1';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
}
