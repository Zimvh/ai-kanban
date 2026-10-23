@php
    $initials = collect(explode(' ', $user->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $colors = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500', 'bg-violet-500', 'bg-teal-500'];
@endphp
<span title="{{ $user->name }}"
      class="inline-grid place-items-center rounded-full text-white font-medium {{ $colors[$user->id % count($colors)] }} {{ $size ?? 'w-6 h-6 text-[10px]' }}">{{ strtoupper($initials) }}</span>
