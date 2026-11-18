{{-- Recursive org-chart node: one staff card + its own <ul> of children. --}}
@php
    $style = $roleBadgeStyle[$node['role']] ?? ['bg' => '#F1F5F9', 'text' => '#475569', 'border' => '#94A3B8'];
@endphp
<li>
    <div
        class="org-card inline-flex flex-col items-center gap-1 px-3 py-2.5 rounded-xl border-2 bg-white shadow-sm w-[150px] text-center org-node {{ $node['in_dept_filter'] ? '' : 'org-node-dimmed' }}"
        style="border-color: {{ $style['border'] }};"
        data-search="{{ strtolower($node['name'] . ' ' . $node['employee_id']) }}"
    >
        <span
            class="w-8 h-8 rounded-full flex items-center justify-center text-[11px] font-black shrink-0"
            style="background:{{ $style['bg'] }};color:{{ $style['text'] }};"
        >{{ strtoupper(substr($node['name'], 0, 1)) }}</span>
        <p class="text-[11px] font-black text-slate-900 leading-tight break-words">{{ $node['name'] }}</p>
        <span
            class="text-[8px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full"
            style="background:{{ $style['bg'] }};color:{{ $style['text'] }};"
        >{{ $node['role'] }}</span>
        {{-- Always shown, separately from position -- a staff member's
             position (e.g. "Client Account Manager") usually says nothing
             about which department they're in, so without this line their
             department was only ever visible via the filter dropdown, not
             by looking at the chart itself. --}}
        <p class="text-[8px] font-black uppercase tracking-wide text-[#B8860B] mt-0.5">{{ $node['department'] }}</p>
        @if($node['position'])
            <p class="text-[9px] text-slate-400 leading-tight break-words">{{ $node['position'] }}</p>
        @endif
        <p class="text-[8px] text-slate-300">{{ $node['employee_id'] }}</p>
    </div>

    @if(!empty($node['children']))
        <ul>
            @foreach($node['children'] as $child)
                @include('org-chart._node', ['node' => $child, 'roleBadgeStyle' => $roleBadgeStyle])
            @endforeach
        </ul>
    @endif
</li>
