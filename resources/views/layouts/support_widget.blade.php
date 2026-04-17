<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support</title>

    {{-- Minimal libs, no navbar/hero here --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/support-chat.css') }}">

    <style>
      html, body {
        height: 100%;
      }
      body.zv-chat-frame-body {
        min-height: 100%;
        margin: 0;
        padding: 8px;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: radial-gradient(circle at top left, #e0f2fe, #f9fafb 45%, #eef2ff 100%);
      }
      .zv-chat-frame-root {
        height: 100%;
      }
    </style>
</head>
<body class="zv-chat-frame-body">
    <div class="zv-chat-frame-root">
        @yield('content')
    </div>

    {{-- jQuery needed for chat JS --}}
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Auto-scroll to bottom of messages container if present --}}
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('zvChatMessages');
        if (list) {
          list.scrollTop = list.scrollHeight;
        }
      });
    </script>

    {{-- Place for page-specific scripts (support/index.blade.php) --}}
    @stack('scripts')
</body>
</html>
