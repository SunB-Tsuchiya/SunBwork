<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: notosansjp, "IPAGothic", "MS Gothic", sans-serif;
    font-size: 10.5pt;
    color: #000;
    background: #fff;
}
.page { width: 190mm; margin: 10mm auto; }

h1 {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    border: 2px solid #000;
    padding: 6px 0;
    margin-bottom: 8px;
}

.header-section { display: table; width: 100%; margin-bottom: 4px; }
.header-row     { display: table-row; }
.header-cell    { display: table-cell; padding: 2px 4px; vertical-align: middle; white-space: nowrap; }
.header-label   { font-weight: bold; }

table.main {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
    table-layout: fixed;
}
table.main th, table.main td {
    border: 1px solid #000;
    padding: 3px 4px;
    font-size: 10pt;
    vertical-align: middle;
}
table.main thead th {
    background: #f0f0f0;
    font-weight: bold;
    text-align: center;
}

.col-date    { width: 10%; }
.col-dest    { width: 14%; }
.col-purpose { width: 17%; }
.col-from    { width: 18%; }
.col-sep     { width:  4%; text-align: center; }
.col-to      { width: 18%; }
.col-amount  { width: 10%; text-align: right; }

td.center { text-align: center; }
td.right  { text-align: right; }

.total-row td { font-weight: bold; background: #f8f8f8; }
.total-label  { text-align: right; }

.note { font-size: 9pt; padding: 5px 2px; }

.stamp-section { display: table; width: 100%; margin-top: 8px; }
.stamp-row     { display: table-row; }
.stamp-left    {
    display: table-cell; width: 28%;
    border: 1px solid #000; min-height: 52px;
    padding: 3px 5px; font-size: 9pt; color: #555; vertical-align: top;
}
.stamp-spacer     { display: table-cell; width: 22%; }
.stamp-right-wrap { display: table-cell; width: 50%; vertical-align: top; }
.stamp-right      { display: table; width: 100%; border-collapse: collapse; border: 1px solid #000; min-height: 52px; }
.stamp-right-row  { display: table-row; }
.stamp-right-cell {
    display: table-cell; width: 33.33%;
    border-right: 1px solid #000;
    padding: 3px; text-align: center; font-size: 9pt; color: #555;
    min-height: 52px; vertical-align: top;
}
.stamp-right-cell:last-child { border-right: none; }
</style>
</head>
<body>
<div class="page">

    <h1>交通費金銭請求書</h1>

    <div class="header-section">
        <div class="header-row">
            <div class="header-cell header-label" style="width:12%;">請求期間</div>
            <div class="header-cell" style="width:36%;">
                @php
                    $s = \Carbon\Carbon::parse($billing->period_start);
                    $e = \Carbon\Carbon::parse($billing->period_end);
                    $rsY = $s->year - 2018; $reY = $e->year - 2018;
                    echo "令和{$rsY}年{$s->month}月{$s->day}日 〜 令和{$reY}年{$e->month}月{$e->day}日";
                @endphp
            </div>
            <div class="header-cell header-label" style="width:10%;">所属</div>
            <div class="header-cell" style="width:42%;">{{ $billing->user->department?->name ?? '' }}</div>
        </div>
        <div class="header-row">
            <div class="header-cell header-label" style="width:12%;"></div>
            <div class="header-cell" style="width:36%;"></div>
            <div class="header-cell header-label" style="width:10%;">氏名</div>
            <div class="header-cell" style="width:42%;">{{ $billing->user->name }}</div>
        </div>
    </div>

    @php
        $allItems = $billing->expenses->flatMap(fn($exp) => $exp->items)->values()->sortBy('occurrence_date');
        $totalRows = 20;
    @endphp

    <table class="main">
        <thead>
            <tr>
                <th class="col-date">発生日</th>
                <th class="col-dest">行先</th>
                <th class="col-purpose">用件</th>
                <th class="col-from">区&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;間</th>
                <th class="col-sep"></th>
                <th class="col-to"></th>
                <th class="col-amount">金額</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $totalRows; $i++)
                @php $item = $allItems[$i] ?? null; @endphp
                <tr>
                    <td class="center">
                        @if($item?->occurrence_date)
                            @php $od = \Carbon\Carbon::parse($item->occurrence_date); @endphp
                            {{ $od->month }}月{{ $od->day }}日
                        @endif
                    </td>
                    <td>{{ $item?->destination ?? '' }}</td>
                    <td>{{ $item ? $item->purpose_label : '' }}</td>
                    <td>{{ $item?->station_from ?? '' }}</td>
                    <td class="center">－</td>
                    <td>{{ $item?->station_to ?? '' }}</td>
                    <td class="right">
                        @if($item && $item->amount > 0)
                            {{ number_format($item->amount) }}
                        @else
                            <span style="color:#aaa;">―</span>
                        @endif
                    </td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="total-label">計</td>
                <td class="right">{{ number_format($billing->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="note">※ 領収証がある場合は裏面に貼付して提出して下さい。</p>

    <div class="stamp-section">
        <div class="stamp-row">
            <div class="stamp-left">経理・受付処理<br><br><br></div>
            <div class="stamp-spacer"></div>
            <div class="stamp-right-wrap">
                <div class="stamp-right">
                    <div class="stamp-right-row">
                        <div class="stamp-right-cell">社長<br><br><br></div>
                        <div class="stamp-right-cell">部門長<br><br><br></div>
                        <div class="stamp-right-cell">申請者<br><br><br></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>
