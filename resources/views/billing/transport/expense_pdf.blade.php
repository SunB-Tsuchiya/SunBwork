<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
{{--
  交通費金銭請求伝票（原本 SB交通費金銭請求伝票_23新.pdf に合わせた配置）。
  原本の寸法（pt。ページ 709.57 x 1002.42）を A4 に縮小して絶対配置する（縮尺 $s）。
  太字用の日本語フォントを入れていないため、太字は使わない（太字にすると文字が「?」になる）。
--}}
@php
    $s  = 595.28 / 709.57;                                   // 原本 → A4 の縮尺
    $p  = fn ($v) => round($v * $s, 2) . 'pt';               // 原本の寸法 → A4 の pt
    $totalBottom = 815.5;                                     // 計の行の下端（原本）
                                               // 罫線ぶん大きく描画されるので、行の高さから差し引く（原本の pt）
        $headTop = 185.5; $firstRowTop = 205.5;
    $dateFmt = function ($d) { return '令和' . ($d->year - 2018) . "年{$d->month}月{$d->day}日"; };
    [$my, $mm] = array_map('intval', explode('-', $expense->billing_month));
    $dash = '－';
@endphp
<style>
@page { margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; font-weight: normal !important; }
body { font-family: notosansjp, "IPAGothic", "MS Gothic", sans-serif; color: #000; background: #fff; }
.sheet { position: relative; width: 595.28pt; height: 841.5pt; overflow: hidden; }
.break { page-break-after: always; }
.abs { position: absolute; white-space: nowrap; }
.c { text-align: center; } .r { text-align: right; } .l { text-align: left; }
.circle { position: absolute; border: 0.7pt solid #000; border-radius: 50%; }
.pill   { position: absolute; border: 0.7pt solid #000; border-radius: 9pt; }
</style>
</head>
<body>
@foreach($pages as $pageIndex => $chunk)
@php
    $last = $pages->count() - 1;
    $pageSum = (int) $chunk->sum('amount');
    $grand = (int) $pages->flatten(1)->sum('amount');
    $bd = $expense->billing_date;
    $fs = $p(10); $fl = $p(11);
    $top = fn ($y) => $p($y - 7.8);                           // 文字の上端(y)から、行ボックスの top を求める（11pt 相当）                              // 表の文字 10pt / ラベル 11pt（原本）
@endphp
<div class="sheet{{ $pageIndex < $last ? ' break' : '' }}">

    {{-- タイトル枠 --}}
    <div class="abs c" style="left:{{ $p(92.5) }}; top:{{ $p(84.5) }}; width:{{ $p(523) }}; height:{{ $p(33.5) }}; border:0.9pt solid #000;"></div>
    <div class="abs c" style="left:{{ $p(92.5 - 8.3) }}; top:{{ $p(91.3 - 12.9) }}; width:{{ $p(523) }}; font-size:{{ $p(18) }};">交通費金銭請求伝票</div>
    {{-- 右上: このサイトから申請したものだと、紙でわかるようにする（総務・経理は、確認・検算をウェブで行える）。複数枚のときは「n / N 枚」も --}}
    <div class="abs r" style="left:{{ $p(415) }}; top:{{ $top(55) }}; width:{{ $p(200.5) }}; font-size:{{ $p(8) }};">{{ $origin }}</div>
    @if($pages->count() > 1)
        <div class="abs r" style="left:{{ $p(515) }}; top:{{ $top(67) }}; width:{{ $p(100.5) }}; font-size:{{ $fs }};">{{ $pageIndex + 1 }} / {{ $pages->count() }} 枚</div>
    @endif

    {{-- 請求日・所属・氏名 --}}
    <div class="abs" style="left:{{ $p(103) }}; top:{{ $top(137.6) }}; font-size:{{ $fl }};">請求日</div>
    <div class="abs" style="left:{{ $p(165.3) }}; top:{{ $top(138) }}; font-size:{{ $fs }};">{{ $dateFmt($bd) }}</div>
    <div class="abs" style="left:{{ $p(262) }}; top:{{ $top(138) }}; font-size:{{ $fs }};">（令和{{ $my - 2018 }}年{{ $mm }}月分）</div>
    <div class="abs" style="left:{{ $p(447.1) }}; top:{{ $top(137.6) }}; font-size:{{ $fl }};">所属</div>
    <div class="abs c" style="left:{{ $p(494.4) }}; top:{{ $top(137.6) }}; width:{{ $p(120) }}; font-size:{{ $fl }};">{{ $companyName }}</div>
    <div class="abs" style="left:{{ $p(447.1) }}; top:{{ $top(154.4) }}; font-size:{{ $fl }};">氏名</div>
    <div class="abs c" style="left:{{ $p(494.4) }}; top:{{ $top(154.4) }}; width:{{ $p(120) }}; font-size:{{ $fl }};">{{ $expense->user->name }}</div>

    {{-- 部門 --}}
    @if($dept['numbered'])
        <div class="abs" style="left:{{ $p(94.8) }}; top:{{ $top(155.4) }}; font-size:{{ $fl }};">部門コード</div>
        @php
            // [コード, 数字の左端x, 名称の左端x, y]（原本の位置）
            $slots = [0 => [159.1, 168.5, 155.4], 10 => [227.3, 242.3, 155.4], 20 => [153.5, 168.5, 172.3], 30 => [227.3, 242.3, 172.3], 50 => [301, 316, 172.3]];
        @endphp
        @foreach($slots as $code => [$nx, $tx, $ly])
            <div class="abs" style="left:{{ $p($nx) }}; top:{{ $top($ly) }}; font-size:{{ $fl }};">{{ $code }}</div>
            <div class="abs" style="left:{{ $p($tx) }}; top:{{ $top($ly) }}; font-size:{{ $fl }};">：{{ $departmentCodes[$code] }}</div>
            @if($dept['selectedCode'] !== null && (int) $dept['selectedCode'] === $code)
                @php $w = $code === 0 ? 5.5 : 11.1; $cx = $nx + $w / 2; @endphp
                <div class="circle" style="left:{{ $p($cx - 9.5) }}; top:{{ $p($ly + 5.5 - 9.5) }}; width:{{ $p(19) }}; height:{{ $p(19) }};"></div>
            @endif
        @endforeach
    @else
        <div class="abs" style="left:{{ $p(94.8) }}; top:{{ $top(155.4) }}; font-size:{{ $fl }};">部門</div>
        <div class="abs" style="left:{{ $p(159) }}; top:{{ $top(155.4) }}; font-size:{{ $fl }};">{{ $dept['name'] }}</div>
        <div class="pill" style="left:{{ $p(153) }}; top:{{ $p(151.5) }}; width:{{ $p(14 + mb_strlen($dept['name']) * 11) }}; height:{{ $p(19) }};"></div>
    @endif

    {{-- 罫線（原本の位置。表は使わず、線と文字をすべて座標で置く） --}}
    @php
        $L = 92.5; $R = 615.5; $lw = 0.75;                      // 表の左右端・線の太さ（原本の pt）
        $rowTops = [];                                          // 各行の上端（ヘッダー行・明細・計）
        $rowTops[] = $headTop;
        for ($i = 0; $i <= $rowsPerPage; $i++) { $rowTops[] = $firstRowTop + $i * (($totalBottom - $firstRowTop) / ($rowsPerPage + 1)); }
        $hline = fn ($y, $x1, $x2) => '<div class="abs" style="left:' . $p($x1) . '; top:' . $p($y - $lw / 2) . '; width:' . $p($x2 - $x1) . '; height:' . $p($lw) . '; background:#000;"></div>';
        $vline = fn ($x, $y1, $y2) => '<div class="abs" style="left:' . $p($x - $lw / 2) . '; top:' . $p($y1 - $lw / 2) . '; width:' . $p($lw) . '; height:' . $p($y2 - $y1 + $lw) . '; background:#000;"></div>';
        // $fo = 文字の大きさ（原本の pt）。行の縦の中心に文字がくるよう、大きさに応じて上端を補正する（10pt で上端 = 行の上端 - 2.8）
        $cell = fn ($x, $y, $w, $align, $text, $fo, $extra = '') => '<div class="abs ' . $align . '" style="left:' . $p($x) . '; top:' . $p($y + 9.8 - 1.258 * $fo) . '; width:' . $p($w) . '; font-size:' . $p($fo) . '; overflow:hidden; ' . $extra . '">' . e($text) . '</div>';
        // 長い文字は、セルに収まるまで文字の大きさを下げる（10pt → 下限 5.5pt。原本の pt で計算して縮尺をかける）
        $fcell = fn ($x, $y, $w, $align, $text) => $cell($x, $y, $w, $align, $text, \App\Services\Billing\Transport\TransportExpenseExporter::fitFontSize((string) $text, $w));
    @endphp
    @foreach($rowTops as $y){!! $hline($y, $L, $R) !!}@endforeach
    {!! $hline($totalBottom, $L, $R) !!}
    @foreach([92.5, 147.5, 258.5, 350.5, 559.5, 615.5] as $x){!! $vline($x, $headTop, $totalBottom) !!}@endforeach

    {{-- ヘッダー行 --}}
    {!! $cell(92.5, $headTop, 55, 'c', '発生日', 10) !!}{!! $cell(147.5, $headTop, 111, 'c', '行先', 10) !!}{!! $cell(258.5, $headTop, 92, 'c', '用件', 10) !!}
    {!! $cell(350.5, $headTop, 209, 'c', '区　　間', 10) !!}{!! $cell(559.5, $headTop, 56, 'c', '金額', 10) !!}

    {{-- 明細行（空の行にも中央の「－」を入れる。原本どおり） --}}
    @for($i = 0; $i < $rowsPerPage; $i++)
        @php $item = $chunk[$i] ?? null; $y = $rowTops[$i + 1]; @endphp
        @if($item?->occurrence_date){!! $cell(92.5, $y, 55, 'c', $item->occurrence_date->month . '月' . $item->occurrence_date->day . '日', 10) !!}@endif
        {!! $fcell(147.5, $y, 111, 'c', $item?->destination ?? '') !!}
        {!! $fcell(258.5, $y, 92, 'c', $item ? \App\Services\Billing\Transport\TransportExpenseExporter::purposeLabel($item) : '') !!}
        {!! $fcell(350.5, $y, 100, 'r', $item?->station_from ?? '') !!}
        {!! $cell(457.4, $y, 11, 'c', $dash, 10) !!}
        {!! $fcell(474.4, $y, 85, 'l', $item?->station_to ?? '') !!}
        @if($item && $item->amount > 0){!! $cell(559.5, $y, 54, 'r', number_format($item->amount), 10) !!}@endif
    @endfor

    {{-- 計（1 枚なら「計」、複数枚なら各枚「小計」・最終枚「合計」） --}}
    @php $y = $rowTops[$rowsPerPage + 1]; @endphp
    {!! $cell(92.5, $y, 55, 'c', $last === 0 ? '計' : ($pageIndex < $last ? '小計' : '合計'), 10) !!}
    {{-- 計の行の中央の「－」は入れない（明細の空行には入れる） --}}
    {!! $cell(559.5, $y, 54, 'r', number_format($pageIndex === $last ? $grand : $pageSum), 10) !!}

    {{-- 注記 --}}
    <div class="abs" style="left:{{ $p(94.9) }}; top:{{ $top(827.1) }}; font-size:{{ $fl }};">※領収証がある場合は裏面に貼付して提出して下さい。</div>

    {{-- 経理・受付処理 / 社長・部門長・申請者 --}}
    {!! $hline(848.5, 92.5, 203.5) !!}{!! $hline(865.5, 92.5, 203.5) !!}{!! $hline(908, 92.5, 203.5) !!}
    {!! $vline(92.5, 848.5, 908) !!}{!! $vline(203.5, 848.5, 908) !!}{!! $vline(147.5, 865.5, 908) !!}
    {!! $hline(848.5, 424.5, 615.5) !!}{!! $hline(865.5, 424.5, 615.5) !!}{!! $hline(908, 424.5, 615.5) !!}
    @foreach([424.5, 492, 559.5, 615.5] as $x){!! $vline($x, 848.5, 908) !!}@endforeach
    <div class="abs c" style="left:{{ $p(92.5) }}; top:{{ $top(851.5) }}; width:{{ $p(111) }}; font-size:{{ $fl }};">経理・受付処理</div>
    <div class="abs c" style="left:{{ $p(424.5) }}; top:{{ $top(851.5) }}; width:{{ $p(67.5) }}; font-size:{{ $fl }};">社長</div>
    <div class="abs c" style="left:{{ $p(492) }}; top:{{ $top(851.5) }}; width:{{ $p(67.5) }}; font-size:{{ $fl }};">部門長</div>
    <div class="abs c" style="left:{{ $p(559.5) }}; top:{{ $top(851.5) }}; width:{{ $p(56) }}; font-size:{{ $fl }};">申請者</div>
</div>
@endforeach
</body>
</html>
