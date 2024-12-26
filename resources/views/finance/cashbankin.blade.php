<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Kas/Bank {{$header[0]->voucher ?? ''}}</title>
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
<div class="header">BUKTI PENERIMAAN KAS/BANK</div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No Voucher</td>
			<td width="280px">:<strong>{{$header[0]->voucher}}</strong></td>
            <td width="100px">Referensi</td>
			<td width="250px">:  {{$header[0]->reference1}}</td>
        </tr>
        <tr>
			<td>Tanggal</td>
			<td>: {{$header[0]->ref_date}}</td>
            <td>Pool</td>
			<td>:  {{$header[0]->pool_code}}</td>
        </tr>
        <tr>
			<td>Kas/Bank</td>
			<td>:  {{$header[0]->bank_account}} - {{$header[0]->account_number}}</td>
            <td>Akun</td>
			<td>: {{$header[0]->no_account}} {{$header[0]->account_name}}</td>
        </tr>
        <tr>
			<td>Total</td>
			<td>: <strong>{{number_format($header[0]->total,2,',','.')}}</strong></td>
            <td>Catatan</td>
			<td>:-</td>
        </tr>
	</table>
</div>
<div class="invoice">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="80px">No. Akun</th>
				<th width="150px">Nama Akun</th>
				<th width="250px">Keterangan</th>
				<th width="80px">Total</th>
			</tr>
		</thead>
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr style="text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->no_account}}</td>
				<td>{{$line->description}}</td>
				<td>{{$line->line_memo}}</td>
				<td align="right">{{number_format($line->amount,2,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
			<tr  style="font-weight:bold">
				<td colspan="4">TOTAL</td>
				<td align="right">{{number_format($amount,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	<table class="tb-row">
		<tr>
			<th width="150px">Dibuat oleh</th>
			<th width="150px">Disetujui oleh</th>
			<th width="150px">Diserahkan oleh</th>
			<th width="150px">Diterima` oleh</th>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">{{$header[0]->user_name}}</td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
	</table>
</div>
<div style="position: absolute; bottom: -30;padding-bottom:10px">
    <div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
</div>
</body>
</html>
