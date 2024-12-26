<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pengeluaran Kas/Bank {{$header[0]->voucher ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 1cm 1cm 1cm 1cm;
		}
		.page-break {
			page-break-before: always;
		}
	</style>
</head>
<body>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>BUKTI PENERIMAAN KS/LAKA</strong></div>
<div class="information">
    <table width="100%" style="padding:0px"> 
        <tr style="padding:0px;margin-bottom:0px">
            <td style="width:200px;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:12px">
No.Dokumen  : <strong>{{$header[0]->doc_number}}</strong>
Tanggal     : {{$header[0]->ref_date}}
Kas/Bank    : {{$header[0]->payment_name}}
Total       : <strong>{{number_format($header[0]->total,2,',','.')}}</strong>
				</pre>
            </td>
            <td style="text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:12px">
Voucher   : <strong>{{$header[0]->voucher}}</strong>
Referensi : {{$header[0]->reference}}
Pool      : {{$header[0]->pool_code}}
Akun      : {{$header[0]->cash_account}}
                </pre>
            </td>
        </tr>

	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="table-detail" style="font-size:12px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="100px">Bukti Setor SPJ</th>
				<th width="300px">Keterangan</th>
				<th width="80px">Sisa KS</th>
				<th width="100px">Bayar</th>
				<th width="50px">No. Akun</th>
			</tr>
		</thead>		
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr  style="font-size:10px;text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->ref_number}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->amount,2,',','.')}}</td>
				<td align="right">{{number_format($line->paid,2,',','.')}}</td>
				<td>{{$line->no_account}}</td>
			</tr>
			@php
			$amount = $amount + $line->paid;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="4">TOTAL DITERIMA</td>
				<td align="right">{{number_format($amount,2,',','.')}}</td>
				<td></td>
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
			<td height="45px">{{$header[0]->user_name}}</td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
		<tr style="text-align:left;vertical-align:bottom;"> 
			<td>Tgl/Jam : {{$header[0]->update_timestamp}}</td>
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
