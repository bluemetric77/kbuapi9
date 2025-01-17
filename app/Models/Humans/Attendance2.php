<?php

namespace App\Models\Humans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Attendance2 extends Model
{
    protected $table = 't_attendance2';
    protected $primaryKey = 'line_id';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
}
