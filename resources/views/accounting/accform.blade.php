<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bukti Jurnal {{$header[0]->voucher ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 0.5cm 1cm 0.5cm;
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
	</style>
</head>
<body>
<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
<div class="header">BUKTI JURNAL</div>
<div class="information">
    <table class="tb-header">
        <tr>
            <td width="80px">No.Voucher</td>
            <td width="350px">: <strong>{{$header[0]->voucher}}</strong></td>
            <td width="80px">Referensi</td>
            <td width="200px">: {{$header[0]->reference1}}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{$header[0]->ref_date}}</td>
            <td>Pool</td>
            <td>: {{$header[0]->pool_code}}</td>
        </tr>
        <tr>
            <td>Catatan</td>
            <td>:{{substr(trim($header[0]->notes),0,100)}}</td>
            <td>Tipe</td>
            <td>: {{$header[0]->descriptions}}</td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="table-detail" style="font-size:12px">
		<thead>
			<tr>
				<th width="70px">No. Akun</th>
				<th width="175px">Nama Akun</th>
				<th width="325px">Keterangan</th>
				<th width="80px">Debet</th>
				<th width="80px">Kredit</th>
			</tr>
		</thead>
		<tbody>
    	    @php $debit=0; $credit=0 @endphp
			@foreach($header as $line)
		    <tr  style="font-size:12px">
				<td>{{$line->no_account}}</td>
				<td>{{$line->description}}</td>
				<td>{{$line->line_memo}}</td>
				<td align="right">{{number_format($line->debit,2,',','.')}}</td>
				<td align="right">{{number_format($line->credit,2,',','.')}}</td>
			</tr>
			@php
			$debit = $debit + $line->debit;
			$credit = $credit + $line->credit;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="3">TOTAL</td>
				<td align="right">{{number_format($debit,2,',','.')}}</td>
				<td align="right">{{number_format($credit,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	<table class="table-detail" style="font-size:12px">
		<tr style="text-align:center;vertical-align:center">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Disetujui oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="50px">{{$header[0]->user_name}}</td>
			<td></td>
		</tr>
		<tr style="text-align:left;vertical-align:bottom;font-size:8px">
			<td>Tgl/Jam :</td>
			<td>Tgl/Jam :</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:12px">
		<div style="font-size:12px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
