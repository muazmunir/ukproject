{{-- Resolves npm-style imports for public/js ES modules (no Vite / Node on server). Pin versions intentionally. --}}
@php
    $esmImportMap = [
        'imports' => [
            '@fullcalendar/core' => 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.19/+esm',
            '@fullcalendar/daygrid' => 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.19/+esm',
            '@fullcalendar/timegrid' => 'https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.19/+esm',
            '@fullcalendar/interaction' => 'https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.19/+esm',
            '@fullcalendar/luxon2' => 'https://cdn.jsdelivr.net/npm/@fullcalendar/luxon2@6.1.19/+esm',
            'luxon' => 'https://cdn.jsdelivr.net/npm/luxon@2.5.2/+esm',
            '@simplewebauthn/browser' => 'https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@13.0.0/+esm',
        ],
    ];
@endphp
<script type="importmap">
{!! json_encode($esmImportMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
