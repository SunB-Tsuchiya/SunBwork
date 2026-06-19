@extends('n_system.demo.layout')

@section('title', '学校一覧')

@section('content')
<style>
    .school-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-bottom: 32px;
    }
    .school-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px 16px;
        text-decoration: none;
        color: #111;
        display: flex;
        flex-direction: column;
        gap: 4px;
        transition: box-shadow 0.15s;
    }
    .school-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        border-color: #93c5fd;
    }
    .school-card-name { font-size: 0.95rem; font-weight: 600; }
    .school-card-meta { font-size: 0.78rem; color: #6b7280; }
    .category-section h2 {
        font-size: 1rem;
        font-weight: 700;
        color: #1a3a6b;
        margin: 24px 0 12px;
        border-left: 4px solid #3b82f6;
        padding-left: 10px;
    }
    .total-info { font-size: 0.85rem; color: #6b7280; margin-bottom: 16px; }
</style>

<p class="total-info">
    登録校: <strong>{{ $grouped->flatten()->count() }}</strong> 校
    &nbsp;|&nbsp;
    <a href="{{ route('n-demo.search') }}">全文検索はこちら</a>
</p>

@foreach($grouped as $category => $schools)
<div class="category-section">
    <h2>
        <span class="ndemo-badge badge-{{ $category }}">{{ $category }}</span>
        &nbsp;{{ $schools->count() }} 校
    </h2>
    <div class="school-grid">
        @foreach($schools as $school)
        <a href="{{ route('n-demo.school', $school->id) }}" class="school-card">
            <span class="school-card-name">{{ $school->name }}</span>
            <span class="school-card-meta">{{ $school->year }}年度</span>
        </a>
        @endforeach
    </div>
</div>
@endforeach

@endsection
