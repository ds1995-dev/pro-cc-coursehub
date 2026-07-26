@props(['value' => 0])
<span {{ $attributes->merge(['class' => 'text-amber-500']) }}>@for($i = 1; $i <= 5; $i++){{ $i <= $value ? '★' : '☆' }}@endfor</span>
