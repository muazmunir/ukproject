@php
  /** @var \App\Models\SupportMessage $msg */
  $meta  = (array)($msg->meta ?? []);
  $event = (string)($meta['event'] ?? 'system');

  $viewerTz = $adminTz
      ?? (auth()->user()->timezone ?? null)
      ?? config('app.timezone');

  $at = $msg->created_at?->timezone($viewerTz);

  // ✅ username-only resolver (fallbacks keep backward compat)
  $u = function (...$keys) use ($meta) {
      foreach ($keys as $k) {
          $v = $meta[$k] ?? null;
          if (is_string($v) && trim($v) !== '') return trim($v);
      }
      return null;
  };

  $icon = 'bi-info-circle';
  $text = $meta['text'] ?? 'System update';

  switch ($event) {

    // -----------------------------------------
    // Agent assignment/join (admin OR manager as agent)
    // -----------------------------------------
    case 'agent_assigned':
    case 'admin_assign':
    case 'agent_taken':
      $icon = 'bi-person-workspace';
      $name = $u('agent_username','staff_username','admin_username', 'agent_name','staff_name','admin_name') ?? 'support';
      $text = "Conversation assigned to {$name}";
      break;

    case 'agent_joined':
    case 'staff_assign':
      $icon = 'bi-person-workspace';
      $name = $u('agent_username','staff_username','admin_username', 'agent_name','staff_name','admin_name') ?? 'support';
      // ✅ never say "manager" here
      $text = "{$name} has joined as agent";
      break;

    // -----------------------------------------
    // Escalation
    // -----------------------------------------
    case 'manager_requested':
      $icon = 'bi-exclamation-circle';
      $name = $u('agent_username','staff_username', 'agent_name','staff_name') ?? 'support';
      $text = "{$name} escalated this conversation to a manager";
      break;

    // ✅ only emitted on escalation assignment
    case 'manager_joined':
      $icon = 'bi-person-check';
      $name = $u('manager_username','staff_username', 'manager_name','staff_name') ?? 'manager';
      $text = "Manager joined: {$name}";
      break;

    case 'manager_ended':
      $icon = 'bi-person-x';
      $name = $u('manager_username','staff_username', 'manager_name','staff_name') ?? 'manager';
      $text = "Manager session ended by {$name}";
      break;

    // -----------------------------------------
    // Close/resolve
    // -----------------------------------------
    case 'staff_ended':
      $icon = 'bi-x-circle';
      $name = $u('staff_username', 'staff_name') ?? 'support';
      $text = "Conversation ended by {$name}";
      break;

    case 'staff_resolved':
      $icon = 'bi-check2-circle';
      $name = $u('staff_username', 'staff_name') ?? 'support';
      $text = "Conversation resolved by {$name}";
      break;
  }

  $stamp = $at ? $at->format('d M Y • H:i') : null;
@endphp

<div class="zv-chat-system-row" data-message-id="{{ $msg->id }}">
  <div class="zv-chat-system-pill text-capitalize">
    <i class="bi {{ $icon }} me-1"></i>
    {{ $text }}

    @if($stamp)
      <span class="ms-2 text-muted small">• {{ $stamp }}</span>
    @endif
  </div>
</div>
