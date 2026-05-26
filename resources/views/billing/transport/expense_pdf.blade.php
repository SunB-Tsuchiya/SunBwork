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

/* タイトル */
h1 {
    text-align: center;
    font-size: 14pt;
    font-weight: bold;
    border: 2px solid #000;
    padding: 6px 0;
    margin-bottom: 8px;
}

/* ヘッダー情報グリッド */
.header-section {
    display: table;
    width: 100%;
    margin-bottom: 4px;
    border-collapse: collapse;
}
.header-row { display: table-row; }
.header-cell { display: table-cell; padding: 2px 4px; vertical-align: middle; white-space: nowrap; }
.header-label { font-weight: bold; }
.header-value { }

/* 部門コードライン */
.dept-line {
    font-size: 9.5pt;
    margin-bottom: 6px;
    padding: 0 4px;
}
.dept-item { display: inline; margin-right: 14px; }
.dept-selected { font-weight: bold; text-decoration: underline; }

/* メインテーブル */
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
td.amount { text-align: right; font-size: 10pt; }
td.empty-amount { text-align: center; color: #aaa; }

/* 合計行 */
.total-row td { font-weight: bold; background: #f8f8f8; }
.total-label  { text-align: right; }

/* 注記 */
.note { font-size: 9pt; padding: 5px 2px; }

/* 押印欄 */
.stamp-section {
    display: table;
    width: 100%;
    margin-top: 8px;
    border-collapse: collapse;
}
.stamp-row { display: table-row; }
.stamp-left {
    display: table-cell;
    width: 28%;
    border: 1px solid #000;
    min-height: 52px;
    padding: 3px 5px;
    font-size: 9pt;
    color: #555;
    vertical-align: top;
}
.stamp-spacer { display: table-cell; width: 22%; }
.stamp-right-wrap {
    display: table-cell;
    width: 50%;
    vertical-align: top;
}
.stamp-right {
    display: table;
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    min-height: 52px;
}
.stamp-right-row { display: table-row; }
.stamp-right-cell {
    display: table-cell;
    width: 33.33%;
    border-right: 1px solid #000;
    padding: 3px;
    text-align: center;
    font-size: 9pt;
    color: #555;
    min-height: 52px;
    vertical-align: top;
}
.stamp-right-cell:last-child { border-right: none; }
</style>
</head>
<body>
<div class="page">

    {{-- タイトル --}}
    <h1>交通費金銭請求伝票</h1>

    {{-- ヘッダー情報 --}}
    <div class="header-section">
        <div class="header-row">
            <div class="header-cell header-label" style="width:12%;">請求日</div>
            <div class="header-cell header-value" style="width:36%;">
                @php
                    $d = \Carbon\Carbon::parse($expense->billing_date);
                    $reiwa = $d->year - 2018;
                    echo "令和{$reiwa}年{$d->month}月{$d->day}日";
                @endphp
            </div>
            <div class="header-cell header-label" style="width:10%;">所属</div>
            <div class="header-cell header-value" style="width:42%;">{{ $expense->user->department?->name ?? '' }}</div>
        </div>
        <div class="header-row">
            <div class="header-cell header-label" style="width:12%;">部門コード</div>
            <div class="header-cell header-value" style="width:36%;">
                @php
                    $codes = [0 => '共通', 10 => '情報出版', 20 => '制作', 30 => '製版', 50 => 'オンデマンド'];
                    $selected = (int)$expense->department_code;
                    $parts = [];
                    foreach($codes as $c => $n) {
                        if ($c === $selected) {
                            $parts[] = "<span style='font-weight:bold;text-decoration:underline;'>{$c}：{$n}</span>";
                        } else {
                            $parts[] = "<span>{$c}：{$n}</span>";
                        }
                    }
                    echo implode(' &nbsp; ', $parts);
                @endphp
            </div>
            <div class="header-cell header-label" style="width:10%;">氏名</div>
            <div class="header-cell header-value" style="width:42%;">{{ $expense->user->name }}</div>
        </div>
    </div>

    {{-- 明細テーブル --}}
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
            @php $totalRows = 20; @endphp
            @for ($i = 0; $i < $totalRows; $i++)
                @php $item = $expense->items[$i] ?? null; @endphp
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
                    <td class="center">{{ $item ? '－' : '－' }}</td>
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
                <td class="right">{{ number_format($expense->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- 注記 --}}
    <p class="note">※ 領収証がある場合は裏面に貼付して提出して下さい。</p>

    {{-- 押印欄 --}}
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
