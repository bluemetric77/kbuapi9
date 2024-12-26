<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SLJ {{$header->doc_number}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 0.5cm 0.5cm 0.5cm;
		}

	</style>
</head>
<body>
<div style="font-size:14px;"><strong>{{$profile->name}}</strong></div>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>SURAT PEMERIKSAAN KENDARAAN</strong></div>
<div class="information">
	<table width="100%">
		<tr>
			<td width="250px" style="text-align:left;vertical-align:top;padding:0px">
				<table style="padding:0px;font-size:12px">
					<tbody>
					<tr>
						<td style="font-size:16px" colspan="2"><strong>{{$header->doc_number}}</strong></td>
					<tr>
						<td width="80px">Tanggal</td>
						<td with="180px">: {{$header->ref_date}}</td>
					</tr>
					<tr>
						<td>Jam</td>
						<td>: {{$header->ref_time}}</td>
					</tr>
					<tr>
						<td>No.Bodi</td>
						<td>: {{$header->vehicle_no}} - {{$header->police_no}}</td>
					</tr>
					<tr>
						<td>KM Service</td>
						<td>:</td>
					</tr>
					<tr>
						<td>Mekanik</td>
						<td>: {{$header->personal_name}}</td>
					</tr>
					<tr>
						<td>Masa Berlaku</td>
						<td>: {{$header->valid_date}}</td>
					</tr>
					</tbody>
				</table>
			</td>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<img src="{{storage_path('app/public/bus.jpeg')}}" width="400px" height="150px" style="float:right">
			</td>
		</tr>
	</table>
</div>
<br/>
<div class="detail">
    <div style="font-size:12px;padding-bottom:5px"><strong>I. FINAL INSPECTION</strong></div>
    <table class="table-detail" width="100%">
        <tr style="font-size:10px">
            <!-- <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='1'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td> -->
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:1.4em;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='1'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  - {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:1.4em;">
				@foreach($detail as $row)
					@if (($row->colidx=='2') && ($row->grpidx=='1'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  - {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 40%;text-align:left;vertical-align:top;line-height:1.4em;">
				@foreach($detail as $row)
					@if (($row->colidx=='3') && ($row->grpidx=='1'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  - {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
        </tr>
	</table>
    <!--
	<br/>
    <table width="100%">
        <tr style="font-size:10px">
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:1em;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='2'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='2'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='2') && ($row->grpidx=='2'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='2') && ($row->grpidx=='2'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='3') && ($row->grpidx=='2'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 40%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='3') && ($row->grpidx=='2'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
        </tr>
	</table>
 	<br/>
   <table width="100%">
        <tr style="font-size:10px">
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='3'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='1') && ($row->grpidx=='3'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='2') && ($row->grpidx=='3'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 30%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='2') && ($row->grpidx=='3'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width:15px;text-align:left;vertical-align:top;line-height:20px;">
				@foreach($detail as $row)
					@if (($row->colidx=='3') && ($row->grpidx=='3'))
						 @if ($row->is_subheader=='1')
						 	<br/>
						 @elseif (($row->is_subheader=='0') && ($row->checked=='1'))
							<input type='checkbox' checked><br/>
						 @else
							<input type='checkbox' unchecked><br/>
						 @endif
					@endif
				@endforeach
            </td>
            <td align="left" style="width: 40%;text-align:left;vertical-align:top;line-height:22px;">
				@foreach($detail as $row)
					@if (($row->colidx=='3') && ($row->grpidx=='3'))
						@if ($row->is_subheader=='1')
						   <strong>{{$row->descriptions}}</strong><br/>
						@else
						  {{$row->descriptions}}<br/>
						@endif
					@endif
				@endforeach
            </td>
        </tr>
	</table> -->
	<br/>
	<div style="font-size:12px;padding-bottom:5px"><strong>II. REKOMENDASI SERVICE</strong></div>
	<p>
		{{$header->recomendation}}
	</p>
	<br/>
	<div style="font-size:12px;padding-bottom:5px"><strong>III. HASIL AKHIR PEMERIKSAAN </strong></div>
	<p>
		<div style="font-size:10">Hasil pemeriksaan unit  {{$header->vehicle_no}} - {{$header->police_no}} :</div>
	</p>
	<br/>
	@php
		$mekanik = $sign['mekanik'];
	@endphp

	<table class="table-detail" style="font-size:10px;line-height:0.5">
		<tr style="border:none;text-align:center;vertical-align:middle;padding:10px">
			<td width="150px">MEKANIK</td>
			<td width="150px">PENGEMUDI</td>
		</tr>
		<tr style="border:none;text-align:center;vertical-align:bottom;padding-top:20px">
			<td height="60px">
				<img src="{{$mekanik}}" width="100px" height="70px"><br/>
				({{$header->personal_name}})</td>
			<td></td>
		</tr>
	</table>
</div>

<div style="position: absolute; bottom: -10;padding-bottom:20px">
    <table  width="100%">
		<tr>
			<td align="left" style="width:50%;">
				dokumen dicetak : {{Date('d-m-Y H:i:s')}}
			</td>
			<td align="right" style="width:50%;">
				diinput oleh : {{$header->update_userid}}, tgl {{$header->update_timestamp}}
			</td>
      </tr>
    </table>
</div>
</body>
</html>
