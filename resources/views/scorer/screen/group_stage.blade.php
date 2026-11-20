<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Group stage screen'])
        @livewireStyles
    </head>
    {{-- Group stage subpage for the venue LED screen: no scorer navigation. --}}
    <body class="led-body min-h-screen">
        <livewire:scorer.group-screen />
        @livewireScripts
    </body>
</html>
