<?php

/** 交通費の確認・検算（総務・経理）の設定 */
return [
    // 当月末日を過ぎたら、その月の新規保存・申請を不可にする（会社のルール）。ローカルで過去の月を試したいときだけ、
    // .env の TRANSPORT_ENFORCE_DEADLINE=false にする。本番は、必ず true（既定）のままにする
    'enforce_deadline' => (bool) env('TRANSPORT_ENFORCE_DEADLINE', true),

    // 期限（月末）を過ぎたあとに、申請を依頼・許可できる月: 何か月前まで（1 = 先月分まで）。それより前の月は、申請できない（支払いの対象外）
    'request_months_back' => (int) env('TRANSPORT_REQUEST_MONTHS_BACK', 1),

    // 1 行の金額が、これを超えたら「金額が大きい」と注意を出す（円）。.env の TRANSPORT_MAX_ROW_AMOUNT で変更できる
    'max_row_amount' => (int) env('TRANSPORT_MAX_ROW_AMOUNT', 5000),

    // 検査済みの運賃が、これより古い（日数）と「要再確認」の印を付ける（運賃改定への備え）
    'recheck_after_days' => (int) env('TRANSPORT_RECHECK_AFTER_DAYS', 365),
];
