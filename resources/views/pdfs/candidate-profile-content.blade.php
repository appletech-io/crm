<h1>{{ $name }}</h1>

@if ($summary)
    <p>{{ $summary }}</p>
@endif

@if ($experienceHtml)
    <h2>Experience</h2>
    {!! $experienceHtml !!}
@endif

@if ($qualificationsHtml)
    <h2>Qualifications</h2>
    {!! $qualificationsHtml !!}
@endif

@if ($skillsHtml)
    <h2>Skills</h2>
    {!! $skillsHtml !!}
@endif
