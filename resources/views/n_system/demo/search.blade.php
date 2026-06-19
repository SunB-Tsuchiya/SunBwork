@extends('n_system.demo.layout')

@section('title', $q ? "「{$q}」の検索結果" : '全文検索')

@section('content')
<style>
    .search-box {
        background: #fff; border-radius: 8px;
        padding: 20px 24px; margin-bottom: 24px; border: 1px solid #e5e7eb;
    }
    .search-box h2 { margin: 0 0 12px; font-size: 1.1rem; color: #1a3a6b; }
    .search-input-row { display: flex; gap: 8px; }
    .search-input-row input[type="text"] {
        flex: 1; padding: 8px 14px;
        border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;
    }
    .search-input-row button {
        padding: 8px 20px; background: #1a3a6b; color: #fff;
        border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem;
    }
    .search-input-row button:hover { background: #2563eb; }
    .result-count { font-size: 0.85rem; color: #6b7280; margin-bottom: 16px; }
    .result-card {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 16px 20px; margin-bottom: 14px;
    }
    .result-card-header {
        display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
    }
    .result-card-school {
        font-weight: 700; color: #1a3a6b; text-decoration: none; font-size: 0.95rem;
    }
    .result-card-school:hover { text-decoration: underline; }
    .result-card-meta { font-size: 0.78rem; color: #6b7280; }
    .result-card-preview {
        font-size: 0.88rem; color: #374151; line-height: 1.6;
        background: #f9fafb; border-radius: 4px; padding: 8px 12px; word-break: break-all;
    }
    .result-card-preview mark { background: #fef08a; padding: 0 2px; border-radius: 2px; }
    .no-result {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 40px; text-align: center; color: #9ca3af;
    }
</style>

<div class="search-box">
    <h2>全文検索</h2>
    <form class="search-input-row" action="{{ route('n-demo.search') }}" method="get">
        <input type="text" name="q" placeholder="例: 平安時代　光合成　方程式…" value="{{ htmlspecialchars($q) }}" autofocus>
        <button type="submit">検索</button>
    </form>
</div>

@if($q !== '')
<p class="result-count">
    「<strong>{{ $q }}</strong>」の検索結果:
    @if($results->isNotEmpty())
        {{ $results->count() }} 件（最大50件）
    @else
        0 件
    @endif
</p>

@if($results->isEmpty())
<div class="no-result">該当する問題が見つかりませんでした。</div>
@else
@foreach($results as $item)
@php
    $preview = mb_substr($item->body_text, 0, 200);
    $highlighted = htmlspecialchars($preview);
    $escaped_q = preg_quote(htmlspecialchars($q), '/');
    $highlighted = preg_replace('/(' . $escaped_q . ')/ui', '<mark>$1</mark>', $highlighted);
@endphp
<div class="result-card">
    <div class="result-card-header">
        <a href="{{ route('n-demo.school', ['id' => $item->school->id, 'tab' => $item->subject, 'mode' => 'Q']) }}"
           class="result-card-school">{{ $item->school->name }}</a>
        <span class="ndemo-badge badge-{{ $item->school->category }}">{{ $item->school->category }}</span>
        <span class="result-card-meta">
            {{ $item->school->year }}年度 &nbsp;
            {{ $subjectLabels[$item->subject] ?? $item->subject }} &nbsp;
            大問{{ $item->daimon_index }}
        </span>
    </div>
    <div class="result-card-preview">{!! $highlighted !!}…</div>
</div>
@endforeach
@endif
@endif

@endsection
