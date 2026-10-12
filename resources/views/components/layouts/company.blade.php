<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-base-100">
        @php
            $authUser = \App\Helpers\CompanyAuth::user();
        @endphp
        <div class="drawer lg:drawer-open">
            <input id="main-sidebar" type="checkbox" class="drawer-toggle" />

            <div class="drawer-content flex flex-col">
                <!-- Mobile Header -->
                <div class="navbar lg:hidden bg-base-200 border-b border-base-300">
                    <div class="navbar-start">
                        <label for="main-sidebar" class="btn btn-ghost btn-square drawer-button lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </label>
                    </div>

                    <div class="navbar-end">
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar placeholder">
                                <div class="w-10 rounded-full bg-primary text-primary-content">
                                    <span class="text-xl">{{ $authUser?->initials() }}</span>
                                </div>
                            </div>
                            <ul tabindex="0" class="overflow-hidden menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                                <li class="menu-title">
                                    <span>{{ $authUser?->name }}</span>
                                    <span class="text-xs">{{ $authUser?->email }}</span>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('company.logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <main class="flex-1 bg-base-200 p-4 lg:p-6">
                    {{ $slot }}
                </main>
            </div>

            <div class="drawer-side">
                <label for="main-sidebar" aria-label="close sidebar" class="drawer-overlay"></label>
                <div class="w-64 min-h-full bg-base-100 border-r border-base-200 flex flex-col">
                    <!-- Logo -->
                    <div class="p-4 border-b border-base-200">
                        <a href="{{ route('home') }}" class="flex items-center space-x-2">
                           <div class="h-8 w-8 rounded-lg overflow-hidden">
                                <img src="{{ asset('android-chrome-192x192.png') }}" alt="Logo" class="h-full w-full object-contain" />
                            </div>
                            <span class="text-lg font-semibold">HTG Tournament</span>
                        </a>
                    </div>

                    <!-- Navigation -->
                    <div class="flex-1 p-4">
                        <ul class="menu menu-vertical w-full">
                            <li>
                                <a href="{{ route('company.dashboard') }}" class="{{ request()->routeIs('company.dashboard') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                    </svg>
                                    Dashboard
                                </a>
                            </li>

                            <li class="menu-title mt-4">
                                <span>Teams</span>
                            </li>

                            <li>
                                <a href="{{ route('company.teams') }}" class="{{ request()->routeIs('company.teams*') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                    My Teams
                                </a>
                            </li>

                            <li class="menu-title mt-4">
                                <span>Company</span>
                            </li>

                            <li>
                                <a href="{{ route('company.profile') }}" class="{{ request()->routeIs('company.profile') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                    </svg>
                                    Company Profile
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Desktop User Menu -->
                    <div class="p-4 border-t border-base-200 hidden lg:block">
                        <div class="dropdown dropdown-top w-full overflow-hidden">
                            <div tabindex="0" role="button" class="flex items-center space-x-2 px-3 py-2 rounded-lg border border-base-300 hover:bg-base-200 transition-colors cursor-pointer w-full">
                                <div class="flex items-center space-x-3 flex-1">
                                    <div class="w-10 h-10 rounded-full bg-primary text-primary-content flex items-center justify-center font-semibold">
                                        {{ $authUser?->initials() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm truncate">{{ $authUser?->name }}</div>
                                        <div class="text-xs opacity-70 truncate">{{ $authUser?->email }}</div>
                                    </div>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 opacity-60">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                </svg>
                            </div>
                            <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-full mb-2">
                                <li>
                                    <form method="POST" action="{{ route('company.logout') }}" class="w-full">
                                        @csrf
                                        <button type="submit" class="flex items-center w-full text-left text-error">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                            </svg>
                                            Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
