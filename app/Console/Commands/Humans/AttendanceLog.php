<?php

namespace App\Console\Commands\Humans;

use Illuminate\Console\Command;
use App\Models\Humans\Attendance1;
use App\Models\Humans\Attendance2;
use App\Models\Humans\Employee;
use App\Models\Humans\AttLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttendanceLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'humans:attlog';

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

    public function handle()
    {
        Log::info('Starting the command execution');
        try {
            $emps=Employee::selectRaw("emp_id,pin")
            ->get();

            foreach($emps as $emp) {
                $date  = new \DateTime();
                $day   = $date->format('d');
                $month = $date->format('m');
                $year  = $date->format('Y');

                if (($day>=21) && ($day<=31)) {
                    $month += 1;
                    if ($month>12) {
                        $month = 1;   // Kembali ke Januari
                        $year += 1;
                    }
                }

                $att1=Attendance1::where('emp_id',$emp->emp_id)
                ->where('month_period',$month)
                ->where('year_period',$year)
                ->where('emp_pin',$emp->pin)
                ->first();
                if (!$att1){
                    $att1=new Attendance1();
                    $att1->emp_id      = $emp->emp_id;
                    $att1->month_period= $month;
                    $att1->year_period = $year;
                    $att1->emp_pin     = $emp->pin;
                    $att1->uuid_rec    = Str::uuid();
                    $att1->save();
                }
                $sysid=$att1->sysid;

                $att2=Attendance2::where('sysid',$sysid)->first();
                if (!$att2) {
                    $eDate= new \DateTime("$year-$month-20");
                    if ($month==1) {
                        $year -= 1;
                        $month = 12;
                    }
                    $sDate = new \DateTime("$year-$month-21");
                    while ($sDate <= $eDate) {
                        $att2=new Attendance2();
                        $att2->sysid      = $sysid;
                        $att2->day_date   = $sDate->format('Y-m-d');
                        $att2->pin        = $emp->pin;
                        $att2->month_period = $att1->month_period;
                        $att2->year_period  = $att1->year_period;
                        $att2->emp_id       = $att1->emp_id;
                        $att2->uuid_rec   = Str::uuid();
                        $att2->save();
                        $sDate->modify('+1 day');
                    }
                }
            }
            Log::info('Build employee attendance successfully');
        } catch (Exception $e) {
            Log::error('Error during execution: ' . $e->getMessage());
        }

        $atts=AttLog::from('AttLog as a')
        ->selectRaw("a.ID, a.PIN, a.AttTime, a.Status, b.emp_id, b.emp_name")
        ->join('m_employee as b', 'a.PIN','=','b.pin')
        ->where('IsProccess',0)
        ->orderBy('PIN','asc')
        ->orderBy('AttTime','asc')
        ->get();

        foreach ($atts as $att) {
            $AttTime = new \DateTime($att->AttTime);
            $Att     = $AttTime->format('Y-m-d H:i');
            $ATime   = $AttTime->format('H:i');
            $date    = $AttTime->format('Y-m-d');
            $status  = $att->Status;  // Assuming this is how status is accessed

            // Log more details to help with debugging
            Log::info('Processing Attendance Log', ['attID' => $att->ID, 'status' => $status, 'date' => $date]);
            if ($status==='1') {

            }
            if ($status === '0') {
                $this->updateAttendance2($att, $Att, $date, 'entry_date');
            } elseif ($status === '1') {
                if (($ATime >'00:01') && ($ATime <'12:00')) {
                    $dt=new \DateTime($date);
                    $dt =$dt->modify('-1 day');
                    $date=$dt->format('Y-m-d');
                }
                $this->updateAttendance2($att, $Att, $date, 'leave_date');
            } elseif ($status === '255') {
                $this->processStatus255($att, $Att, $date);
            }

            // Mark the log entry as processed
            AttLog::where('ID', $att->ID)
                ->update(['IsProccess' => '1']);
        }

        DB::update("UPDATE t_attendance2 SET work_hour=TIMESTAMPDIFF(SECOND,entry_date,leave_date)
                    WHERE year_period=? AND month_period=? AND leave_date IS NOT NULL AND entry_date IS NOT NULL",[$year,$month]);

        Log::info('Completed processing all Attendance Logs');
        return Command::SUCCESS;
    }

    protected function updateAttendance2($att, $Att, $date, $field)
    {
        Attendance2::where('pin', $att->PIN)
            ->where('day_date', $date)
            ->update([
                $field => $Att,
                'leave_status' => '',
                'leave_notes' => ''
            ]);
    }

    /**
     * Process status 255, checking if entry_date is already set.
     */
    protected function processStatus255($att, $Att, $date)
    {
        $att2 = Attendance2::where('pin', $att->PIN)
            ->where('day_date', $Att)
            ->first();

        if ($att2) {
            if (!$att2->entry_date) {
                Attendance2::where('pin', $att->PIN)
                    ->where('day_date', $date)
                    ->update([
                        'entry_date' => $Att,
                        'leave_status' => '',
                        'leave_notes' => ''
                    ]);
            } else {
                Attendance2::where('pin', $att->PIN)
                    ->where('day_date', $date)
                    ->update([
                        'leave_date' => $Att,
                        'leave_status' => '',
                        'leave_notes' => ''
                    ]);
            }
        }
    }
}
