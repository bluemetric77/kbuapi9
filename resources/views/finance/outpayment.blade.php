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
<div class="header">BUKTI PEMBAYARAN HUTANG</div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No.Voucher</td>
			<td width="280px">:<strong>{{$header[0]->doc_number}}</strong> - {{$header[0]->voucher}}</td>
            <td width="100px">Supplier</td>
			<td width="250px">: {{$header[0]->partner_name}}</td>
        </tr>
        <tr>
			<td>Tanggal</td>
			<td>: {{$header[0]->ref_date}}</td>
            <td><Strong>Pembayaran</strong></td>
			<td>: <strong>{{number_format($header[0]->paid,2,',','.')}}</strong></td>
        </tr>
        <tr>
			<td><Strong>Kas/Bank</td>
			<td colspan="3">: {{$header[0]->payment_method}}</td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="150px">No. Invoice</th>
				<th width="150px">Referensi/Acuan</th>
				<th width="150px">Hutang</th>
				<th width="150px">Pembayaran</th>
			</tr>
		</thead>
		<tbody>
    	    @php $payment=0; @endphp
			@foreach($header as $line)
		    <tr>
				<td>{{$line->line_no}}</td>
				<td>{{$line->doc}} {{$line->invoice_number}}</td>
				<td>{{$line->doc}} {{$line->reference}}</td>
				<td align="right">{{number_format($line->total,0,',','.')}}</td>
				<td align="right">{{number_format($line->payment,0,',','.')}}</td>
			</tr>
			@php
			$payment = $payment + $line->payment;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="4">TOTAL</td>
				<td align="right">{{number_format($payment,0,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	<table class="tb-row">
		<tr style="text-align:center;vertical-align:center">
			<td width="150px">Dibuat</td>
			<td width="150px">Diperiksa</td>
			<td width="150px">DiSetujui</td>
			<td width="150px">Diterima</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">({{$header[0]->user_name}})</td>
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
