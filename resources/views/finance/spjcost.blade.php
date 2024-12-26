<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pengeluaran Kas/Bank {{$header[0]->voucher ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 30px;
		}
	</style>
</head>
<body>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>BUKTI PENGELUARAN BIAYA OPS.KENDARAAN</strong></div>
<div class="information">
    <table width="100%" style="padding:0px"> 
        <tr style="padding:0px;margin-bottom:0px">
            <td style="width:200px;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:12px">
No.Voucher  : <strong>{{$header[0]->doc_number}}</strong> - {{$header[0]->voucher}}
Tanggal     : {{$header[0]->ref_date}}
Kas/Bank    : {{$header[0]->bank_account}} - {{$header[0]->account_number}}
Total       : <strong>{{number_format($header[0]->total,2,',','.')}}</strong>
				</pre>
            </td>
            <td style="text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:12px">
SPJ     : {{$header[0]->link_document}}
Pool    : {{$header[0]->pool_code}}
Akun    : {{$header[0]->cash_account}}
Unit    : {{$header[0]->vehicle_no}} - {{$header[0]->police_no}} 
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
				<th width="500px">Keterangan/th>
				<th width="150px">Total</th>
			</tr>
		</thead>		
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr  style="font-size:10px;text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->line_memo}} {{$line->driver_name}}</td>
				<td align="right">{{number_format($line->amount,2,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="2">TOTAL</td>
				<td align="right">{{number_format($amount,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
	<table class="table-detail" style="font-size:10px">
		<tr style="text-align:center;vertical-align:center"> 
			<td width="150px">Dibuat</td>
			<td width="150px">Diperiksa</td>
			<td width="150px">DiSetujui</td>
			<td width="150px">Diterima</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;"> 
			<td height="45px">({{$header[0]->user_name}})</td>
			<td></td>
			<td></td>
			<td></td>
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
