<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Hutang {{$header[0]->voucher ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 1cm 1cm 1cm;
            font-family: "Times New Roman", Times, serif;
		}
		.page-break {
			page-break-before: always;
		}
        .header {
            font-family: "Times New Roman", Times, serif;
            font-size:20px;
            text-align:center;
            margin-bottom:10px;
            font-weight:bold;
        }
        .tb-header {
            font-family: "Times New Roman", Times, serif;
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
            padding:5px 2px;
        }
	</style>
</head>
<body>
<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
<div class="header"><strong>RENCANA PEMBAYARAN SUPPLIER</strong></div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No.Pengajuan</td>
			<td width="280px">:<strong>{{$header[0]->doc_number}}</strong></td>
            <td width="100px">Supplier</td>
			<td width="250px">: {{$header[0]->partner_name}}</td>
        </tr>
        <tr>
			<td>Tanggal</td>
			<td>: {{$header[0]->ref_date}}</td>
            <td>Total Hutang</td>
			<td>: {{number_format($header[0]->total,0,',','.')}}</td>
        </tr>
        <tr>
			<td>Kas/Bank</td>
			<td>: {{$header[0]->payment_method}}</td>
            <td>Rencana Bayar</td>
			<td>: <strong>{{number_format($header[0]->payment,0,',','.')}}</strong></td>
        </tr>
	</table>
</div>
<div class="invoice">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="120px">No. Invoice</th>
				<th width="100px">Reference</th>
				<th width="200px">Keterangan</th>
				<th width="100px">Hutang</th>
				<th width="100px">Rencana</th>
			</tr>
		</thead>
		<tbody>
    	    @php $payment=0; @endphp
			@foreach($header as $line)
		    <tr>
				<td>{{$line->line_no}}</td>
				<td>{{$line->invoice_number}}</td>
				<td>{{$line->reference}}</td>
				<td>{{$line->notes}}</td>
				<td align="right">{{number_format($line->invoice_total,0,',','.')}}</td>
				<td align="right">{{number_format($line->plan,0,',','.')}}</td>
			</tr>
			@php
			$payment = $payment + $line->payment;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="5">TOTAL</td>
				<td align="right">{{number_format($payment,0,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	<div class="header-title">INSTRUKSI PEMBAYARAN</div>
	<table class="tb-row">
		<tr>
			<td width="120px">Cara Pembayaran</td>
			<td width="300px">{{$header[0]->default_payment}}</td>
		</tr>
		<tr>
			<td>Bank</td>
			<td width="300px">{{$header[0]->bank_name}}</td>
		</tr>
		<tr>
			<td>No. Rekening</td>
			<td>{{$header[0]->bank_account}}</td>
		</tr>
		<tr>
			<td>Nama Rekening</td>
			<td>{{$header[0]->account_name}}</td>
		</tr>
	</table>
	<br/>
	@php
	$user = $sign['user'];
	$general_manager = $sign['general_manager'];
	$director = $sign['director'];
	@endphp
	<table class="tb-row">
		<tr style="text-align:center;vertical-align:center;font-weight:bold">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Diketahui oleh</td>
			<td width="150px">Disetujui oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">
				<img src="{{$user}}" width="100px" height="100px"><br/>
				{{$header[0]->user_name}}</td>
			<td>
				<img src="{{$general_manager}}" width="100px" height="100px"><br/>
				Aep Hardianto</td>
			<td>
				<img src="{{$director}}" width="100px" height="100px"><br/>
				M. Andriana Ruhyana, SE</td>
		</tr>
		<tr style="text-align:center;vertical-align:middle">
			<td>User</td>
			<td>Keuangan</td>
			<td>Direktur</td>
		</tr>
		<tr style="text-align:left;vertical-align:middle">
			<td>Tgl {{isset($header[0]->update) ? $header[0]->update:''}}</td>
			<td>Tgl {{isset($header[0]->approved2) ? $header[0]->approved1:''}}</td>
			<td>Tgl {{isset($header[0]->approved2) ? $header[0]->approved2:''}}</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
