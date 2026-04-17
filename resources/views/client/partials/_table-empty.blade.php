<div class="table-responsive">
    <table>
      <thead>
        <tr>
          @for($i=0;$i<$cols;$i++)
            <th>&nbsp;</th>
          @endfor
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="{{ $cols }}" class="zv-empty-hero">
            <div class="zv-empty-emoji">🧳</div>
            <div class="zv-empty-title">{{ $message }}</div>
            <div class="zv-empty-sub">{{ __('You’ll see items here once you have activity.') }}</div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  