<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Pembelian {{$header->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 50px;
		}

	</style>
</head>
<body>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>INVOICE PEKERJAAN</strong></div>
<div class="information">
    <table width="100%" style="padding:0px">
        <tr style="padding:0px;margin-bottom:0px">
            <td style="width:50%;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:10px">
No.Dokumen  : <strong>{{$header->doc_number}}</strong>
Tanggal     : {{$header->ref_date}}
Supplier    : {{$header->partner_name}}
				</pre>
            </td>
            <td style="width:50%;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:10px">
Inv.Supplier : {{$header->ref_document}}
Jatuh tempo  : {{$header->due_date}}
Pool         : {{$header->pool_code}}
Unit         : {{$header->vehicle_no}} - {{$header->police_no}} - {{$header->unit_notes}}
                </pre>
            </td>
        </tr>

	</table>
</div>
<br/>
<div class="invoice" style="margin-left:0px">
	<table class="table-detail" style="font-size:10px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="350px">Keterangan</th>
				<th width="40px">Jumlah</th>
				<th width="80px">Harga</th>
				<th width="40px">Diskon</th>
				<th width="100px">Total</th>
			</tr>
		</thead>
		<tbody>
    	    @php $total=0; @endphp
			@foreach($detail as $line)
		    <tr  style="font-size:10px;text-align:left;vertical-align:top">
				<td align="center">{{$line->line_no}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->qty_invoice,0,',','.')}}</td>
				<td align="right">{{number_format($line->price,0,',','.')}}</td>
				<td align="right">{{number_format($line->discount,2,',','.')}}</td>
				<td align="right">{{number_format($line->total,2,',','.')}}</td>
			</tr>
			@php
			$total = $total + $line->total;
			@endphp
			@endforeach
			<tr  style="font-size:11px;font-weight:bold">
				<td colspan="5">Jumlah</td>
				<td align="right">{{number_format($header->amount,2,',','.')}}</td>
			</tr>
			<tr  style="font-size:11px;font-weight:bold">
				<td colspan="5">Diskon</td>
				<td align="right">{{number_format($header->discount1,2,',','.')}}</td>
			</tr>
			<tr  style="font-size:11px;font-weight:bold">
				<td colspan="5">Total</td>
				<td align="right">{{number_format($header->net_total,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
	<br/>
	<br/>
	@php
	$user_sign = $sign['user_sign'];
	@endphp
	<table class="table-detail" style="font-size:12px">
		<tr style="text-align:center;vertical-align:center;font-weight:bold">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Diperiksa oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">
				<img src="{{$user_sign}}" width="100px" height="100px"><br/>
				{{$header->user_name}}</td>
			<td></td>
		</tr>
		<tr style="text-align:left;vertical-align:bottom;font-size:10px">
			<td>Tgl/Jam</td>
			<td>Tgl/Jam</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
		<table width="100%">
			<tr style="font-size:10px">
				<td align="left" style="width: 50%;">
					&copy; {{ date('Y') }} {{ config('app.url') }}
				</td>
				<td align="right" style="width:50%;">
					{{$profile->name}}
				</td>
			</tr>
		</table>
	</div>
</body>
</html>
