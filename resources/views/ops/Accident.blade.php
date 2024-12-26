<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Kecelakaan Lalulintas {{$header->doc_number}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 50px;
		}

	</style>
</head>
<body>

<div style="font-size:14px;"><strong>{{$profile->name}}</strong></div>
<div style="font-size:18px;text-align:center;margin-bottom:10px"><strong>BERITA ACARA LAKA</strong></div>
<div class="information">
                <pre style="line-height: 2.0;">
Berita Acara    : <strong>{{$header->doc_number}}</strong>
Tanggal         : {{$header->ref_date}}
Pengemudi       : {{$header->driver}}
Unit            : {{$header->vehicle_no}} - {{$header->police_no}}
Tgl Kejadian    : {{$header->accident_date}}
Lokasi Kejadian : {{$header->accident_location}}
				</pre>
<br/>
<br/>
<strong>Berita Acara</strong>

    <pre style="line-height: 2.0;">
{!!$header->notes !!}
    </pre>                
</div>
<br/>
<br/>
<div class="information"><strong>Beban Biaya</strong></div>
<pre style="line-height: 2.0;">
Total Biaya LAKA       : <strong>{{number_format($header->cost,2,',','.')}}</strong>
Ditanggung Kantor      : {{number_format($header->office_cost,2,',','.')}}
Ditanggung Pengemudi   : {{number_format($header->driver_cost,2,',','.')}}
				</pre>
<br/>
<br/>
<br/>
	<table class="table-detail" style="font-size:10px">
		<tr style="text-align:center;vertical-align:center"> 
			<td width="150px">Dibuat</td>
			<td width="150px">Pengemudi</td>
			<td width="150px">DiSetujui</td>
			<td width="150px">DiPeriksa</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;"> 
			<td></td>
			<td height="45px">({{$header->driver}})</td>
			<td></td>
			<td></td>
		</tr>
	</table>

<div class="information" style="position: absolute; bottom: -30;">
    <table width="100%">
        <tr style="font-size:10px">
            <td align="left" style="width: 50%;">
                &copy; {{ date('Y') }} {{ config('app.url') }} - All rights reserved.
            </td>
            <td align="right" style="width:50%;">
                {{$profile->name}}
            </td>
        </tr>

    </table>
</div>
</body>
</html>