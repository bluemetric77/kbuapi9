<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Kas/Bank-Intransit {{$header[0]->voucher ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 0.5cm 0.5cm 0.5cm;
		}
		.page-break {
			page-break-before: always;
		}
        .header {
           font-size:14px;
           text-align:center;
           margin-bottom:14px;
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
        }
       .header-title {
            font-weight:bold;
            background-color: black;
            color: white;
            padding:2px 5px;
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
<div class="header"><strong>BUKTI SETOR KAS/BANK (INTRANSIT)</strong></div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No Dokumen</td>
			<td width="280px">:<strong>{{$header[0]->voucher}}</strong></td>
            <td width="100px">Tanggal</td>
			<td width="250px">: {{$header[0]->ref_date}}</td>
        </tr>
        <tr>
			<td>No. Voucher</td>
			<td>: <strong>{{$header[0]->voucher}}</strong></td>
            <td>Referensi</td>
			<td>:  {{$header[0]->reference1}}</td>
        </tr>
        <tr>
			<td>Kas/Bank</td>
			<td>: {{trim($header[0]->account_number)}} -{{$header[0]->bank_account}}</td>
            <td>Pool</td>
			<td>: {{$header[0]->pool_code}}</td>
        </tr>
        <tr>
			<td>Akun</td>
			<td>: {{$header[0]->kas}} - {{$header[0]->account_name}}</td>
            <td>Total</td>
			<td>: <strong>{{number_format($header[0]->total,2,',','.')}}</strong></td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="30px">No</th>
				<th width="100px">Dokumen</th>
				<th width="320px">Keterangan</th>
				<th width="100px">Akun</th>
				<th width="80px">Total</th>
			</tr>
		</thead>
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr  style="font-size:12px;text-align:left;vertical-align:top">
				<td align="center">{{$line->line_no}}</td>
				<td>{{$line->doc_number_line}}</td>
				<td>{{$line->descriptions_line}}</td>
				<td align="center">{{$line->no_account}}</td>
				<td align="right">{{number_format($line->amount,2,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="4">TOTAL</td>
				<td align="right">{{number_format($amount,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	@php
	$user = $sign['user'];
	@endphp
	<table class="table-detail" style="font-size:12px">
		<tr style="text-align:center;vertical-align:center">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Diketahui oleh</td>
			<td width="150px">Disetujui oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">
				<img src="{{$user}}" width="100px" height="100px"><br/>
				{{$header[0]->user_name}}
			</td>
			<td>
			</td>
			<td>
			</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td>Tgl {{isset($header[0]->update_timestamp) ?date_format($header[0]->update_timestamp,'d-m-Y H:i'):''}}</td>
			<td>Tgl/Jam</td>
			<td>Tgl/Jam</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
