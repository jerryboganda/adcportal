<header
    class="dash-header {{ empty($company_settings['site_transparent']) || $company_settings['site_transparent'] == 'on' ? 'transprent-bg' : '' }} ">
    <div class="header-wrapper">
        <div class="me-auto dash-mob-drp">
            <ul class="list-unstyled gap-2">
                <li class="dash-h-item mob-hamburger">
                    <a href="#!" class="dash-head-link m-0" id="mobile-collapse">
                        <div class="hamburger hamburger--arrowturn">
                            <div class="hamburger-box">
                                <div class="hamburger-inner"></div>
                            </div>
                        </div>
                    </a>
                </li>

                <li class="dropdown dash-h-item drp-company">
                    <a class="dash-head-link dropdown-toggle arrow-none m-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false"aria-expanded="false">
                        @if (Auth::check() && !empty(Auth::user()->avatar))
                            <span class="theme-avtar">
                                <img alt="#"
                                    src="{{ check_file(Auth::user()->avatar) ? get_file(Auth::user()->avatar) : '' }}"
                                    class="rounded border-2 border border-primary" style="width: 100% ; height: 100%">
                            </span>
                        @elseif (Auth::check())
                            <span class="theme-avtar">{{ substr(Auth::user()->name, 0, 1) }}</span>
                        @endif
                        @if (Auth::check())
                            <span class="hide-mob ms-2">{{ Auth::user()->name }}</span>
                        @endif
                        <i class="ti ti-chevron-down drp-arrow nocolor hide-mob"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown">
                        @permission('user profile manage')
                            <a href="{{ route('profile') }}" class="dropdown-item">
                                <i class="ti ti-user"></i>
                                <span>{{ __('Profile') }}</span>
                            </a>
                        @endpermission
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                            class="dropdown-item">
                            <i class="ti ti-power"></i>
                            <span>{{ __('Logout') }}</span>
                        </a>
                        <form id="frm-logout" action="{{ route('logout') }}" method="POST" class="d-none">
                            {{ csrf_field() }}
                        </form>
                    </div>
                </li>

            </ul>
        </div>
        <div class="ms-auto">
            <ul class="list-unstyled gap-2">
                {{-- Single-clinic app: impersonation exit, Create Business button and
                     business-switcher dropdown removed. --}}
                @permission('business manage')
                <li class="dash-h-item">
                    <a href="{{ route('business.manage', getActiveBusiness()) }}"
                       class="dash-head-link dropdown-toggle arrow-none m-0 cust-btn"
                       data-bs-placement="bottom" data-bs-original-title="Clinic Settings">
                        <i class="ti ti-apps"></i>
                        <span class="hide-mob">{{ Auth::check() ? Auth::user()->ActiveBusinessName() : '' }}</span>
                    </a>
                </li>
                @endpermission

                <li class="dropdown dash-h-item drp-language">
                    <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-world"></i>
                        <span class="drp-text hide-mob">{{ Str::upper(getActiveLanguage()) }}</span>
                        <i class="ti ti-chevron-down drp-arrow"></i>
                    </a>
                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">

                        @foreach (languages() as $key => $language)
                            <a href="{{ route('lang.change', $key) }}"
                                class="dropdown-item @if ($key == getActiveLanguage()) text-danger @endif">
                                <span>{{ Str::ucfirst($language) }}</span>
                            </a>
                        @endforeach
                        {{-- Single-clinic app: language management always available. --}}
                        @permission('language create')
                            <a href="#" data-url="{{ route('create.language') }}"
                                class="dropdown-item border-top pt-3 text-primary" data-ajax-popup="true"
                                data-title="{{ __('Create New Language') }}">
                                <span>{{ __('Create Language') }}</span>
                            </a>
                        @endpermission
                        @permission('language manage')
                            <a href="{{ route('lang.index', [Auth::user()->lang]) }}"
                                class="dropdown-item  pt-3 text-primary">
                                <span>{{ __('Manage Languages') }}</span>
                            </a>
                        @endpermission
                    </div>
                </li>
            </ul>
        </div>
    </div>
</header>
