<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Super Admin — ZAIVIAS')</title>

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="{{ asset('assets/css/admin-top.css') }}">

  @stack('styles')
</head>

<body style="background:#f4f5f7;">
  
  {{-- LOGIN + PUBLIC PAGES ONLY --}}
  <main class="zv-container py-5">
      @yield('content')
  </main>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  @stack('scripts')
</body>
</html>
