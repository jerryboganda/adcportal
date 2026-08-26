@props(['state'])

@php
    $studyState = $state instanceof \App\Enums\StudyState
        ? $state
        : \App\Enums\StudyState::tryFrom((string) $state);
@endphp

@if ($studyState)
    <span {{ $attributes->merge(['class' => 'badge ' . $studyState->color()]) }}
        data-study-state="{{ $studyState->value }}">{{ $studyState->label() }}</span>
@endif
