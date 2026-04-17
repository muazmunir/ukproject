<div class="settings-subnav">
    <a href="{{ route('superadmin.settings.trainer.edit') }}"
       class="subtab {{ request()->routeIs('superadmin.settings.trainer.*') ? 'active' : '' }}">
      Coaches Settings
    </a>
  
    <a href="{{ route('superadmin.settings.customer.edit') }}"
       class="subtab {{ request()->routeIs('superadmin.settings.customer.*') ? 'active' : '' }}">
      Client Settings
    </a>
  
    <a href="{{ route('superadmin.settings.appearance.edit') }}"
       class="subtab {{ request()->routeIs('superadmin.settings.appearance.*') ? 'active' : '' }}">
      Site Customization
    </a>
  </div>
  

  