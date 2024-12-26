<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPJ {{$header->doc_number}}</title>
	<style>
		@page  {
			margin: 0.5cm 0.5cm 0.5cm 0.5cm;
		}
		.page-break {
			page-break-before: always;
		}
        .header {
           font-size:26px;
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
        .tb-row {
            width:100%;
            font-size:14px;
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
            font-size:14px;
        }
        .header-title {
            font-weight:bold;
            padding:5px 2px;
        }
	</style>
</head>
<body>

<img src="data:image/png;base64, {!! $qrcode !!}" style="float:right">
<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
<div clas="header">SURAT PERINTAH JALAN</div>
<div class="information">
    <table class="tb-header">
		<tr>
			<td colspan="2" style="font-size:16px;font-weight:bold">{{$header->doc_number}}</td>
			<td>Pengemudi</td>
			<td>: {{$header->driver}}</td>
		</tr>
		<tr>
			<td width="70px">Tanggal</td>
			<td width="350px">: {{$header->ref_date}}</td>
			<td width="90px">Tagihan LAKA</td>
			<td>: {{number_format($header->debt_accident,0,',','.')}}</td>
		</tr>
		<tr>
			<td>Jam</td>
			<td>: {{$header->time_boarding}}</td>
			<td>Kondektur</td>
			<td>: {{$header->conductor}}</td>
		</tr>
		<tr>
			<td>Unit</td>
			<td>: {{$header->vehicle_no}} - {{$header->police_no}}</td>
			<td>Tagihan KS</td>
			<td>: {{number_format($header->debt_deposit,0,',','.')}}</td>
		</tr>
		<tr>
			<td>Rute</td>
			<td>: {{$header->route_name}}</td>
			<td>Kernet</td>
			<td>: {{$header->helper}}</td>
		</tr>
	</table>
</div>

<div class="invoice">
    <div class="header-title"><strong>BERANGKAT</strong></div>
    <table class="tb-row">
		<thead>
			<tr>
				<th width="220px">Pos Kontrol</th>
				<th width="60px">Point</th>
				<th width="140px">Terbilang</th>
				<th width="50px">Jam</th>
				<th width="80px">TTD Pos Control</th>
			</tr>
		</thead>
		<tbody>
			@php $i=1 @endphp
			@foreach($go as $p)
			<tr>
				<td height="35" style="font-size:14px">{{$p->checkpoint_name}}</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
			@endforeach
		</tbody>
    </table>
	<br/>
    <div class="header-title" style="padding-top:5px;padding-bottom:5px;"><strong>PULANG</strong></div>
    <table class="tb-row">
		<thead>
			<tr>
				<th width="220px">Pos Kontrol</th>
				<th width="60px">Point</th>
				<th width="140px">Terbilang</th>
				<th width="50px">Jam</th>
				<th width="80px">TTD Pos Control</th>
			</tr>
		</thead>
		<tbody>
			@php $i=1 @endphp
			@foreach($back as $p)
			<tr>
				<td height="35" style="font-size:14px">{{$p->checkpoint_name}}</td>
				<!--<td align="center" style="font-size:14px">{{number_format($p->factor_point,2,',','.')}}</td> -->
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>
			@endforeach
		</tbody>
    </table>
</div>
<div class="information">
    <table width="100%" style="padding:0px;font-size:12px">
        <tr style="padding:0px;margin-bottom:0px">
			<td align="left" style="width:94%;text-align:left;vertical-align:top">
<p style="line-height:1.5;">
<strong>Harap diperhatikan untuk petugas checker</strong><br/>
1. Penulisan jumlah penumpang & jam harus jelas<br/>
2. Wajib membubuhkan cap & tandatangan<br/>
3. Koreksi jumlah penumpang wajib diberikan keterangan<br/>
4. Wajib diperiksa/dicocokan crew tertulis dengan yang jalan
</p>
            </td>
            <td align="right" style="width: 50%;text-align:left;vertical-align:top;padding:0px">
			<table class="table-detail" style="font-size:10px">
				<tr>
					<td style="width:80px; height:20px">SETOR</td>
					<td style="width:150px">.</td>
				</tr>
				<tr>
					<td style="width:80px; height:20px">BAYAR KS</td>
					<td></td>
				</tr>
				<tr>
					<td style="width:80px; height:20px">BAYAR LAKA</td>
					<td></td>
				</tr>
			</table>
        </tr>

	</table>
</div>
<table>
	<tr>
		<td>
			<table class="table-detail" style="font-size:10px">
				<tr align="center">
					<td width="150px">Pengemudi</td>
					<td width="150px">Kondektur</td>
					<td width="150px">Kernet</td>
				</tr>
				<tr style="text-align:center;vertical-align:bottom;">
					<td height="60"></td>
					<td></td>
					<td></td>
				</tr>
				<tr style="text-align:center;vertical-align:bottom;">
					<td>{{$header->driver}}</td>
					<td>{{$header->conductor}}</td>
					<td>{{$header->helper}}</td>
				</tr>
			</table>
		</td>
		<td>
			<table class="table-detail" style="font-size:10px">
				<tr style="text-align:center;vertical-align:center;">
					<td colspan="2">Petugas</td>
				</tr>
				<tr style="text-align:center;vertical-align:bottom;">
					<td height="60" width="115px">
						<img src="{{$sign}}" height="60px"><br/>
						{{$header->user_name}}</td>
					<td width="115px" style="font-size:10px"></td>
				</tr>
				<tr style="text-align:center;vertical-align:bottom;">
					<td width="115px">Berangkat</td>
					<td width="115px" style="font-size:10px">Pulang</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<table class="table-detail" style="font-size:10px;width:100%;margin-left:3px">
	<tr style="text-align:left;vertical-align:top;">
		<td height="45px">Keluhan/Keterangan</td>
	</tr>
</table>
<div class="information" style="position: absolute; bottom: -30;">
    <table width="100%">
        <tr style="font-size:10px">
            <td align="right" style="width:50%;">
                {{$profile->name}}
            </td>
        </tr>

    </table>
</div>
<div style="position: absolute; bottom: -30;padding-bottom:10px">
	<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
</div>

</body>
</html>
