<?php

namespace App\Http\Controllers\Reports;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Config\Users;
use PagesHelp;

class GeneratorController extends Controller
{
    public function getReport(Request $request){
      $sysid=isset($request->sysid) ?  $request->sysid : '-1';
      $data = DB::table('o_reports')
         ->select('report_title', 'columns', 'parameter','url_data','col_format')
         ->where('id', $sysid)
         ->first();
      return response()->success('Success',$data);
    }
}
