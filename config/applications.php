<?php

/** 申請機能（交通費など）の利用権限（申請者管理）の設定 */
return [
    // 申請を使う権限（申請者管理のスイッチ）を、サーバー側でも強制する。OFF の人は、画面に入れない。
    // ローカルで確認するときだけ、.env の APPLICATION_ENFORCE_ACCESS=false にする（全員が使える）。本番は、必ず true（既定）のままにする
    'enforce_access' => (bool) env('APPLICATION_ENFORCE_ACCESS', true),
];
