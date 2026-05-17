@php
    $iconClass = trim('cc-icon ' . ($class ?? ''));
@endphp
@switch($name ?? 'dot')
    @case('dashboard')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5 8 2.5l5.5 4V13a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V6.5z"/><path d="M6.5 13.5V9h3v4.5"/></svg></span>
        @break
    @case('source')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="8" cy="4.5" rx="5" ry="2"/><path d="M3 4.5v7c0 1.1 2.24 2 5 2s5-.9 5-2v-7"/><path d="M3 8c0 1.1 2.24 2 5 2s5-.9 5-2"/></svg></span>
        @break
    @case('development')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13.5V6.5l5-3.5 5 3.5v7"/><path d="M6.5 13.5V10h3v3.5"/></svg></span>
        @break
    @case('run')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/><path d="M8 5v3.2l2 1.3"/></svg></span>
        @break
    @case('change')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 3.5 13.5 6 11 8.5"/><path d="M2.5 6h11"/><path d="M5 12.5 2.5 10 5 7.5"/><path d="M13.5 10H2.5"/></svg></span>
        @break
    @case('issue')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2.5 14 13.5H2L8 2.5z"/><path d="M8 6.5v3"/><circle cx="8" cy="11.5" r=".5" fill="currentColor" stroke="none"/></svg></span>
        @break
    @case('check')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-6"/></svg></span>
        @break
    @case('cross')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4.5 4.5 7 7"/><path d="m11.5 4.5-7 7"/></svg></span>
        @break
    @case('info')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="5.5"/><path d="M8 7v4"/><circle cx="8" cy="5.25" r=".5" fill="currentColor" stroke="none"/></svg></span>
        @break
    @case('filter')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4h11l-4 5v3.5L7.5 14V9L3.5 4z"/></svg></span>
        @break
    @case('logout')
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3.5H3.5a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5H6"/><path d="M10.5 11l2.5-3-2.5-3"/><path d="M5.5 8h7.5"/></svg></span>
        @break
    @default
        <span class="{{ $iconClass }}" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="8" cy="8" r="2"/></svg></span>
@endswitch
