<?php

namespace App\Console\Commands\Accounting;

use Illuminate\Console\Command;
use Accounting;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Master\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Reposting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:reposting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function RePosting($sysid){
        $info=[
            'state'=>false,
            'message'=>''
        ];

        $jurnal1 = Journal1::
        selectRaw("
        sysid,
        ref_date,
        fiscal_month,
        fiscal_year,
        is_verified,
        is_void,
        trans_code,
        trans_series,
        notes")
        ->where('sysid', $sysid)
        ->first();

        $message="";

        if (!$jurnal1) {
            $message='Data tidak ditemukan - ('.$sysid.')';
            exit();
        }
        //Log::info($jurnal1->trans_code.' - '.$jurnal1->trans_series);

        $setup=Accounting::Config();

        if (($setup->earning_account=='') || (!($setup->earning_account))) {
            return ['state'=>false,'message'=>'Akun Laba/rugi berjalan belum di setup'];
            exit();
        } else if (($setup->retainearning_account=='') || (!($setup->retainearning_account))) {
            return ['state'=>false,'message'=>'Akun Laba/rugi ditahan belum di setup'];
            exit();
        }

        $isExists=Account::select('account_no')
        ->where('account_no',$setup->earning_account)
        ->where('is_posted','1')
        ->where('is_active','1')
        ->first();

        if (!($isExists)){
            return ['state'=>false,'Akun Laba/rugi berjalan salah/tidak terdaftar dimaster akun'];
            exit();
        }

        $isExists=Account::select('account_no')
        ->where('account_no',$setup->retainearning_account)
        ->where('is_posted','1')
        ->where('is_active','1')
        ->first();

        if (!($isExists)){
            return ['state'=>false,'Akun Laba/rugi ditahan salah/tidak terdaftar dimaster akun'];
            exit();
        }

        $jurnal = Journal2::from('t_jurnal2 as a')
        ->selectRaw("
            a.sysid,
            a.no_account,
            a.project,
            a.debit,
            a.credit,
            a.description,
            a.line_memo,
            a.reference1,
            c.ref_date,
            c.trans_code,
            IFNULL(b.project_mandatory, 0) as project_mandatory,
            a.fiscal_month,
            a.fiscal_year,
            IFNULL(b.is_active, 0) as is_active,
            IFNULL(b.is_posted, 0) as is_posted,
            IFNULL(b.account_no, '') as account_no,
            IFNULL(b.intransit, 0) as intransit,
            c.pool_code,
            c.update_timestamp,
            c.update_userid,
            a.transtype,
            c.is_void,
            b.account_group
        ")
        ->leftJoin('m_account as b', 'a.no_account', '=', 'b.account_no')
        ->leftJoin('t_jurnal1 as c', 'a.sysid', '=', 'c.sysid')
        ->where('a.sysid', $sysid)
        ->get();

        $debit =0;
        $credit=0;

        foreach($jurnal as $row){
            if ((($row->project=='00') || ($row->project=='')) && ($row->project_mandatory=='1')){
                $info['message']='Akun '.$row->no_account.'-'.$row->line_memo.' belum diisi proyek-nya';
                return $info;
                exit();
            } else if ($row->account_no==''){
                $info['message']='Akun '.$row->no_account.'-'.$row->description.' tidak ditemukan ('.$row->line_memo.')';
                return $info;
                exit();
            } else if ($row->is_active==0){
                $info['message']='Akun '.$row->no_account.'-'.$row->description.' sudah tidak aktif ('.$row->line_memo.')';
                return $info;
                exit();
            } else if ($row->is_posted==0){
                $info['message']='Akun '.$row->no_account.'-'.$row->description.' adalah akun header (tidak bisa di posting)';
                return $info;
                exit();
            }
            $debit=$debit+floatval($row->debit);
            $credit=$credit+floatval($row->credit);
        }

        $debit  = round($debit,2);
        $credit = round($credit,2);
        if (!($debit==$credit)) {
            $info['message']='Jurnal Debit & Kredit tidak balance/sama  DEBIT :'.$debit.' KREDIT :'.$credit;
            return $info;
            exit();
        }

        $earning=$setup->earning_account;
        $retain=$setup->retainearning_account;
        foreach($jurnal as $row){
            $month=$row->fiscal_month;
            $year=$row->fiscal_year;
            $field_debit='debit_month'.$month;
            $field_credit='credit_month'.$month;
            $row->project= in_array($row->account_group,[1,2,3]) ? '00' :$row->project;


            //Check if exists
            $mutasi=DB::table('t_account_mutation')
                ->where('fiscal_year',$year)
                ->where('no_account',$row->no_account)
                ->where('project',$row->project)
                ->first();
            if (!$mutasi) {
                DB::table('t_account_mutation')
                ->insert([
                    'fiscal_year'=>$year,
                    'no_account'=>$row->no_account,
                    'project'=>$row->project,
                    'begining_balance'=>0
                ]);
            }
            DB::update(
                "UPDATE t_account_mutation
                SET
                $field_debit=IFNULL($field_debit,0)+?,
                $field_credit=IFNULL($field_credit,0)+?
                WHERE fiscal_year=? AND no_account=? AND project=?",
                [$row->debit,$row->credit,$year,$row->no_account,$row->project]);

            /* Earning Account */
            if (substr($row->no_account,0,1)>'3') {
                //Check if Exists
                $mutasi=DB::table('t_account_mutation')
                    ->where('fiscal_year',$year)
                    ->where('no_account',$earning)
                    ->where('project',$row->project)
                    ->first();
                if (!$mutasi) {
                    DB::table('t_account_mutation')
                    ->insert([
                        'fiscal_year'=>$year,
                        'no_account'=>$earning,
                        'project'=>$row->project,
                        'begining_balance'=>0
                    ]);
                }
                DB::update(
                    "UPDATE t_account_mutation
                    SET
                    $field_debit=IFNULL($field_debit,0)+?,
                    $field_credit=IFNULL($field_credit,0)+?
                    WHERE fiscal_year=? AND no_account=? AND project=?",
                    [$row->debit,$row->credit,$year,$earning,$row->project]);
            }
        }

        $info['state']=true;
        return $info;
    }

    public function handle()
    {
        $month = 1;
        $year  = 2024;
        $array=[1,2,3,4,5,6,7,8,9,10,11];
        for($i=1;$i<12;$i++) {
            Log::info('Bulan :'.$i);

            $month  = $i;
            $debit  = 'debit_month'.strval($month);
            $credit = 'credit_month'.strval($month);
            DB::update(
                "UPDATE t_account_mutation
                SET $debit=0,$credit=0
                WHERE fiscal_year=?",[$year]
            );

            $data=Journal1::selectRaw("sysid")
            ->where('fiscal_year',$year)
            ->where('fiscal_month',$month)
            ->update([
                'is_posted'=>'0'
            ]);

            $data=Journal1::selectRaw("sysid")
            ->where('fiscal_year',$year)
            ->where('fiscal_month',$month)
            ->get();
            foreach($data as $row){
                DB::beginTransaction();
                try{
                    $response=$this->RePosting($row->sysid);
                    Journal1::where('sysid',$row->sysid)
                    ->update([
                        'is_posted'=>'1'
                    ]);
                    DB::commit();
                } catch (Exception $e) {
                    Log::info($row->sysid.$e->getMessage());
                    DB::rollback();
                }
            }
            Sleep(5);
        }
        return Command::SUCCESS;
    }
}
