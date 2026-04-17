<div class="settings-subnav">
    <a href="{{ route('admin.settings.trainer.edit') }}"
       class="subtab {{ request()->routeIs('admin.settings.trainer.*') ? 'active' : '' }}">
      Coaches Settings
    </a>
  
    <a href="{{ route('admin.settings.customer.edit') }}"
       class="subtab {{ request()->routeIs('admin.settings.customer.*') ? 'active' : '' }}">
      Client Settings
    </a>
  
    <a href="{{ route('admin.settings.appearance.edit') }}"
       class="subtab {{ request()->routeIs('admin.settings.appearance.*') ? 'active' : '' }}">
      Site Customization
    </a>
  </div>
  

  