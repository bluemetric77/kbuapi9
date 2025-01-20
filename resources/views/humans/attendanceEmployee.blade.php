<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Absensi Karyawan</title>
	<style>
		@page  {
			margin: 10px 30px 20px 20px;
            font-size:12px;
		}
        .header {
           font-size:20px;
           text-align:center;
           margin-bottom:10px;
           font-weight:bold;
        }
        .tb-header {
            width:100%;
            font-size:12px;
        }
        .tb-header tr {
            text-align:left;
            vertical-align:top;
            padding:0px;
        }
        .tb-header tr td {
            text-align:left;
            vertical-align:top;
        }
        .tb-row {
            width:100%;
            font-size:12px;
            border: 0.5px solid black;
            border-collapse: collapse;
        }
        .tb-row td,
        tr,
        th {
            padding: 5px;
            border: .5px solid black;
        }
        .tb-row th {
            background-color: #f0f0f0;
        }
        .header-title {
            font-weight:bold;
            background-color: black;
            color: white;
            padding:2px 5px;
        }
	</style>

</head>
<body>

<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
<div clas="header">ABSENSI KARYAWAN</div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="80px"><Strong>NIP</strong></td>
			<td width="500px">: {{$attendance->emp_id}}</td>
        </tr>
        <tr>
			<td><Strong>Nama</strong></td>
			<td>: {{$attendance->emp_name}}</td>
        </tr>
        <tr>
			<td><Strong>Periode</strong></td>
			<td>: {{$attendance->month_period}} - {{$attendance->year_period}}</td>
        </tr>
	</table>
</div>
<br/>
<table class="tb-row">
    <thead>
        <tr>
            <th width="70px">Hari</th>
            <th width="60px">Tanggal</th>
            <th width="60px">Masuk</th>
            <th width="60px">Keluar</th>
            <th width="80px">Status</th>
            <th width="200px">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($details as $line)
            @php
                $date= new \DateTime($line->day_date);
                $line->day_date= $date->format('d-m-Y');

                if ($line->entry_date) {
                    $entry= date_create($line->entry_date);
                    $line->entry_date= $entry->format('H:i');
                }
                if ($line->leave_date) {
                    $leave= date_create($line->leave_date);
                    $line->leave_date= $leave->format('H:i');
                }
            @endphp
            <tr>
                <td>{{$line->day_name}}</td>
                <td>{{$line->day_date}}</td>
                <td align="center">{{$line->entry_date}}</td>
                <td align="center">{{$line->leave_date}}</td>
                <td>{{$line->leave_status}}</td>
                <td>{{$line->leave_notes}}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<div style="position: absolute; bottom: -30;padding-bottom:10px">
	<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
</div>

</body>
</html>
