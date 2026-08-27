<header class="dash-header {{ empty($company_settings['site_transparent']) || $company_settings['site_transparent'] == 'on' ? 'transprent-bg' : '' }}">
    <div class="header-wrapper">
        <div class="me-auto dash-mob-drp">
            <ul class="list-unstyled gap-2">
                <li class="dash-h-item mob-hamburger">
                    <a href="#!" class="dash-head-link m-0" id="mobile-collapse" aria-label="{{ __('Toggle navigation') }}">
                        <div class="hamburger hamburger--arrowturn" aria-hidden="true">
                            <div class="hamburger-box">
                                <div class="hamburger-inner"></div>
                            </div>
                        </div>
                    </a>
                </li>

                <li class="dropdown dash-h-item drp-company">
                    <a class="dash-head-link dropdown-toggle arrow-none m-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false" aria-label="{{ __('Account menu') }}">
                        @if (Auth::check() && !empty(Auth::user()->avatar))
                            <span class="theme-avtar">
                                <img alt="{{ Auth::user()->name }}"
                                    src="{{ check_file(Auth::user()->avatar) ? get_file(Auth::user()->avatar) : '' }}"
                                    class="rounded border-2 border border-primary" style="width: 100% ; height: 100%">
                            </span>
                        @elseif (Auth::check())
                            <span class="theme-avtar">{{ substr(Auth::user()->name, 0, 1) }}</span>
                        @endif
                        @if (Auth::check())
                            <span class="hide-mob ms-2">{{ Auth::user()->name }}</span>
                        @endif
                        <i class="ti ti-chevron-down drp-arrow nocolor hide-mob" aria-hidden="true"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown">
                        @permission('user profile manage')
                            <a href="{{ route('profile') }}" class="dropdown-item">
                                <i class="ti ti-user" aria-hidden="true"></i>
                                <span>{{ __('Profile') }}</span>
                            </a>
                        @endpermission
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                            class="dropdown-item">
                            <i class="ti ti-power" aria-hidden="true"></i>
                            <span>{{ __('Logout') }}</span>
                        </a>
                        <form id="frm-logout" action="{{ route('logout') }}" method="POST" class="d-none">
                            {{ csrf_field() }}
                        </form>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</header>
