<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Fiscalyears;
use App\Models\Master\Account;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Accounting\AccountMutation;
use App\Models\Accounting\AccountMutationCheck;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class FiscalYearsController extends Controller
{
    public function show(Request $request){
        $validator=Validator::make($request->all(),[
            'fiscalyear'=>'bail|required',
        ],[
            'fiscalyear.required'=>'Periode tahun akunting harus disii',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $session=PagesHelp::Session();

        $month = array("Januari", "Februari", "Maret","April","Mei","Juni","Juli",
                      "Agustus","September","Oktober","November","Desember");
        $years = $request->fiscalyear;
        for ($imonth = 1; $imonth <= 12; $imonth+=1) {
            $counter = str_pad($imonth, 2 ,"0", STR_PAD_LEFT);
            $period  = $years.$counter;

            $fyperiode = Fiscalyears::where('fiscal_id',$period)
            ->first();
            if (!$fyperiode) {
                $start_date= date_create($years.'-'.$counter.'-01');

                $pyperiod= new Fiscalyears();
                $pyperiod->fiscal_id   = $period;
                $pyperiod->enum_status = "Open";
                $pyperiod->is_closed   = 0;
                $pyperiod->year_period   = $years;
                $pyperiod->month_period  = $imonth;
                $pyperiod->descriptions  = $month[$imonth-1].' '.$years;
                $pyperiod->start_date    = date_format($start_date,'Y-m-d');
                $start_date->modify('last day of this month');
                $pyperiod->end_date      = date_format($start_date,'Y-m-d');
                $pyperiod->update_userid = $session->user_id;
                $pyperiod->save();
            }
        }
        $data= Fiscalyears::selectRaw("fiscal_id,year_period,month_period,descriptions,start_date,end_date,
        enum_status,is_closed,close_date")
        ->where('year_period',$years)
        ->orderBy('month_period','asc')
        ->paginate(12);

        return response()->success('Success',$data);
    }

    public function GLAnalysis(Request $request) {
        $validator=Validator::make($request->all(),[
            'fiscal_year'=>'bail|required',
            'fiscal_month'=>'bail|required',
        ],[
            'fiscal_year.required'=>'Periode tahun akunting harus disii',
            'fiscal_month.required'=>'Periode bulan akuting harus diisi ',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $fperiod= Fiscalyears::where('year_period',$request->fiscal_year)
        ->where('month_period',$request->fiscal_month)
        ->first();

        if (!$fperiod) {
            return response()->error('',501,'Periode akunting tidak ditemukan');
        }

        DB::beginTransaction();
        try{
            AccountMutationCheck::where('fiscal_year',$request->fiscal_year)
            ->where('fiscal_month',$request->fiscal_month)
            ->delete();

            $projects =DB::table('m_project')->selectRaw("project_code")
            ->where('is_active',1)
            ->get();

            foreach($projects as $project ){
                DB::insert("INSERT INTO t_account_mutation_check(fiscal_year,fiscal_month,project,no_account)
                SELECT ?,?,?,account_no FROM m_account
                WHERE is_posted=1 AND is_active=1",[
                    $request->fiscal_year,
                    $request->fiscal_month,
                    $project->project_code
                ]);
            }

            /* get data from balance account */
            $debit="debit_month".$request->fiscal_month;
            $credit="credit_month".$request->fiscal_month;

            DB::update(
            "UPDATE t_account_mutation_check amc
            INNER JOIN t_account_mutation am
            ON
                amc.fiscal_year = am.fiscal_year AND
                amc.no_account=am.no_account AND
                amc.project=am.project
            SET
            amc.debit=IFNULL($debit,0),
            amc.credit=IFNULL($credit,0)
            WHERE amc.fiscal_year=?",[
                $request->fiscal_year,
            ]);

            /* get data from GL*/
            DB::update(
            "UPDATE t_account_mutation_check amc
            INNER JOIN
            (SELECT a.fiscal_year,a.fiscal_month,a.no_account,SUM(a.debit) as debit, SUM(a.credit) as credit
                FROM t_jurnal2 a
                INNER JOIN m_account b ON a.no_account=b.account_no
                WHERE a.fiscal_year =? AND a.fiscal_month =? AND b.account_group IN (1,2,3)
                GROUP BY a.fiscal_year,a.fiscal_month,a.no_account) j2
            ON
                amc.fiscal_year = j2.fiscal_year AND
                amc.fiscal_month = j2.fiscal_month AND
                amc.no_account=j2.no_account AND
                amc.project='00'
            SET
            amc.gl_debit=IFNULL(j2.debit,0),
            amc.gl_credit=IFNULL(j2.credit,0)
            WHERE amc.fiscal_year=? AND amc.fiscal_month=? AND amc.project='00'",[
                $request->fiscal_year,
                $request->fiscal_month,
                $request->fiscal_year,
                $request->fiscal_month
            ]);

            DB::update(
            "UPDATE t_account_mutation_check amc
            INNER JOIN
            (SELECT a.fiscal_year,a.fiscal_month,a.project,a.no_account,SUM(a.debit) as debit, SUM(a.credit) as credit
                FROM t_jurnal2 a
                INNER JOIN m_account b ON a.no_account=b.account_no
                WHERE a.fiscal_year =? AND a.fiscal_month =? AND b.account_group NOT IN (1,2,3)
                GROUP BY a.fiscal_year,a.fiscal_month,a.project,a.no_account) j2
            ON
                amc.fiscal_year = j2.fiscal_year AND
                amc.fiscal_month = j2.fiscal_month AND
                amc.no_account=j2.no_account AND
                amc.project=j2.project
            SET
            amc.gl_debit=IFNULL(j2.debit,0),
            amc.gl_credit=IFNULL(j2.credit,0)
            WHERE amc.fiscal_year=? AND amc.fiscal_month=?",[
                $request->fiscal_year,
                $request->fiscal_month,
                $request->fiscal_year,
                $request->fiscal_month
            ]);

            DB::update(
            "UPDATE t_account_mutation_check
            SET is_valid= CASE
            WHEN debit=gl_debit AND credit=gl_credit THEN 1
            ELSE 0 END
            WHERE fiscal_year=? AND fiscal_month=?",[
                $request->fiscal_year,
                $request->fiscal_month
            ]);

            DB::commit();
            return response()->success('Success','Proses selesai');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function PeriodeSetup(Request $request) {
        $validator=Validator::make($request->all(),[
            'fiscal_year'=>'bail|required',
            'fiscal_month'=>'bail|required',
            'status'=>'bail|required',
        ],[
            'fiscal_year.required'=>'Periode tahun akunting harus disii',
            'fiscal_month.required'=>'Periode bulan akuting harus diisi ',
            'status.required'=>'Status periode akuting harus diisi ',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $fperiod= Fiscalyears::where('year_period',$request->fiscal_year)
        ->where('month_period',$request->fiscal_month)
        ->first();

        if (!$fperiod) {
            return response()->error('',501,'Periode akunting tidak ditemukan');
        }
        DB::beginTransaction();
        try{
            Fiscalyears::where('year_period',$request->fiscal_year)
            ->where('month_period',$request->fiscal_month)
            ->update([
                'enum_status' => $request->status,
                'is_closed'=>($request->status=='Open') ? '0' :'1',
                'close_date'=>($request->status=='Open') ? null :Date('Y-m-d'),
            ]);
            DB::commit();
            return response()->success('Success','Ubah status period akunting berhasil');
        }catch(Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function ShowAnalytic(Request $request) {
        $validator=Validator::make($request->all(),[
            'fiscal_year'=>'bail|required',
            'fiscal_month'=>'bail|required',
        ],[
            'fiscal_year.required'=>'Periode tahun akunting harus disii',
            'fiscal_month.required'=>'Periode bulan akuting harus diisi ',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $filter        = $request->filter;
        $limit         = $request->limit;
        $sorting       = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy        = $request->sortBy;
        $fiscal_year   = $request->fiscal_year;
        $fiscal_month  = $request->fiscal_month;

        $data= Account::from("m_account as a")
        ->selectRaw("a.account_no,a.account_name,SUM(b.debit) as debit,SUM(b.credit) as credit,
        SUM(b.gl_debit) as gl_debit,SUM(b.gl_credit) as gl_credit,MIN(b.is_valid) as is_valid")
        ->join("t_account_mutation_check as b",function($join) use ($fiscal_year,$fiscal_month) {
            $join->on("a.account_no","=","b.no_account");
            $join->on("b.fiscal_year","=",DB::raw($fiscal_year));
            $join->on("b.fiscal_month","=",DB::raw($fiscal_month));
        })
        ->groupBy("a.account_no")
        ->groupBy("a.account_name");


        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('descriptions','like',$filter)
               ->orwhere('a.account_no','like',$filter)
               ->orwhere('a.account_name','like',$filter);
        }

        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }
}
