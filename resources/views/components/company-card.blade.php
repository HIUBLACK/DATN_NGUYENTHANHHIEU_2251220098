@props(['company'])
<article class="company-card" id="company-{{ $company['id'] }}"><span class="company-logo {{ $company['color'] }}">{{ $company['initials'] }}</span><h3>{{ $company['name'] }}</h3><p>{{ $company['sector'] }}</p><div class="muted small">{{ $company['location'] }} · {{ $company['size'] }}</div><a href="{{ route('jobs', ['q'=>$company['name']]) }}">Khám phá cơ hội ↗</a></article>
