@extends('n_system.demo.layout')

@section('title', $school->name . ' ' . $school->year . '年度')

@section('content')
<style>
    .school-header {
        background: #fff;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 20px;
        border: 1px solid #e5e7eb;
    }
    .school-header h2 { margin: 0 0 4px; font-size: 1.3rem; color: #1a3a6b; }
    .school-header-meta {
        font-size: 0.85rem; color: #6b7280;
        display: flex; gap: 12px; align-items: center;
    }
    .tab-row { display: flex; gap: 4px; margin-bottom: 4px; flex-wrap: wrap; }
    .tab-link {
        padding: 6px 18px;
        border-radius: 6px 6px 0 0;
        border: 1px solid #d1d5db;
        border-bottom: none;
        background: #f3f4f6;
        text-decoration: none;
        color: #374151;
        font-size: 0.9rem;
    }
    .tab-link.active { background: #1a3a6b; color: #fff; border-color: #1a3a6b; }
    .tab-link:hover:not(.active) { background: #e5e7eb; }
    .tab-link.disabled { color: #d1d5db; cursor: default; pointer-events: none; }
    .mode-row {
        display: flex; gap: 8px; margin-bottom: 16px;
        border-top: 2px solid #1a3a6b; padding-top: 12px;
    }
    .mode-link {
        padding: 4px 14px; border-radius: 4px;
        border: 1px solid #d1d5db; background: #f9fafb;
        text-decoration: none; color: #374151; font-size: 0.85rem;
    }
    .mode-link.active { background: #059669; color: #fff; border-color: #059669; }
    .daimon-block {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 20px 24px; margin-bottom: 20px;
        scroll-margin-top: 20px;
    }
    .daimon-block:target {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        animation: ndemo-target-highlight 2.5s ease-out;
    }
    .daimon-block mark.ndemo-search-hit {
        background: #fde047;
        color: #111827;
        padding: 1px 2px;
        border-radius: 3px;
        box-shadow: 0 0 0 1px rgba(202, 138, 4, 0.2);
    }
    @keyframes ndemo-target-highlight {
        from { background: #dbeafe; }
        to { background: #fff; }
    }
    .daimon-label { font-size: 0.78rem; color: #9ca3af; margin-bottom: 8px; }
    .back-link {
        display: inline-block; margin-bottom: 16px;
        color: #1a3a6b; text-decoration: none; font-size: 0.85rem;
    }
    .back-link:hover { text-decoration: underline; }
    .no-data {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 40px; text-align: center; color: #9ca3af;
    }
</style>

<a href="{{ route('n-demo.index') }}" class="back-link">← 学校一覧に戻る</a>

<div class="school-header">
    <h2>{{ $school->name }}</h2>
    <div class="school-header-meta">
        <span class="ndemo-badge badge-{{ $school->category }}">{{ $school->category }}</span>
        <span>{{ $school->year }}年度</span>
    </div>
</div>

<div class="tab-row">
    @foreach($subjectLabels as $code => $label)
    @php $isAvailable = in_array($code, $availableSubjects); @endphp
    <a href="{{ route('n-demo.school', ['id' => $school->id, 'tab' => $code, 'mode' => $mode]) }}"
       class="tab-link {{ $tab === $code ? 'active' : '' }} {{ !$isAvailable ? 'disabled' : '' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="mode-row">
    <a href="{{ route('n-demo.school', ['id' => $school->id, 'tab' => $tab, 'mode' => 'Q']) }}"
       class="mode-link {{ $mode === 'Q' ? 'active' : '' }}">問題</a>
    <a href="{{ route('n-demo.school', ['id' => $school->id, 'tab' => $tab, 'mode' => 'A']) }}"
       class="mode-link {{ $mode === 'A' ? 'active' : '' }}">解答</a>
</div>

@if($daimons->isEmpty())
<div class="no-data">
    {{ $subjectLabels[$tab] }} の{{ $mode === 'Q' ? '問題' : '解答' }}データがありません
</div>
@else
@foreach($daimons as $daimon)
<div class="daimon-block" id="daimon-{{ $daimon->daimon_index }}">
    <div class="daimon-label">大問 {{ $daimon->daimon_index }}</div>
    {!! $daimon->body_html !!}
</div>
@endforeach
@endif

@if(!empty($highlightTerms))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const highlightTerms = @json($highlightTerms);
    const targetId = decodeURIComponent(window.location.hash.slice(1));
    const target = targetId ? document.getElementById(targetId) : null;
    if (!target || !highlightTerms.length) return;

    const highlightTerm = (term) => {
        const walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                if (!node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                if (node.parentElement?.closest('script, style, mark, rt, rp')) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            },
        });
        const nodes = [];
        let text = '';
        while (walker.nextNode()) {
            nodes.push({ node: walker.currentNode, start: text.length });
            text += walker.currentNode.nodeValue;
        }

        const haystack = text.toLocaleLowerCase('ja');
        const needle = term.toLocaleLowerCase('ja');
        if (!needle) return;

        const matches = [];
        let position = 0;
        while ((position = haystack.indexOf(needle, position)) !== -1) {
            matches.push({ start: position, end: position + needle.length });
            position += needle.length;
        }

        nodes.forEach(({ node, start }) => {
            const end = start + node.nodeValue.length;
            const segments = matches
                .filter((match) => match.start < end && match.end > start)
                .map((match) => ({
                    start: Math.max(0, match.start - start),
                    end: Math.min(node.nodeValue.length, match.end - start),
                }))
                .sort((a, b) => b.start - a.start);

            segments.forEach((segment) => {
                const after = node.splitText(segment.end);
                const matched = node.splitText(segment.start);
                const mark = document.createElement('mark');
                mark.className = 'ndemo-search-hit';
                matched.parentNode.insertBefore(mark, matched);
                mark.appendChild(matched);
                if (!after.nodeValue) after.remove();
            });
        });
    };

    highlightTerms.forEach(highlightTerm);
    requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
});
</script>
@endif

@endsection
