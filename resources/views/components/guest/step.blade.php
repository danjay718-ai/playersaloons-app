@props(['number', 'title', 'body' => null])
<li class="gr-step gr-reveal"><span class="gr-step-no" aria-hidden="true">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $title }}</h3>@if($body)<p>{{ $body }}</p>@endif</li>
