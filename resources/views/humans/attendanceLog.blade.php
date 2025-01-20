<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log Absensi Harian</title>
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
<div clas="header">ABSENSI HARIAN</div>

<table class="tb-row">
    <thead>
        <tr>
            <th width="50px">Pool</th>
            <th width="60px">No.Pegawai</th>
            <th width="60px">PIN</th>
            <th width="200px">Nama Karyawan</th>
            <th width="100px">Tanggal</th>
            <th width="80px">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($att as $line)
            @php
                $date= new \DateTime($line->AttTime);
                $line->AttTime= $date->format('Y-m-d H:i');
            @endphp
            <tr>
                <td>{{$line->pool_code}}</td>
                <td>{{$line->emp_id}}</td>
                <td>{{$line->PIN}}</td>
                <td>{{$line->emp_name}}</td>
                <td>{{$line->AttTime}}</td>
                <td>{{$line->Status}}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<div style="position: absolute; bottom: -30;padding-bottom:10px">
	<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
</div>

</body>
</html>
