<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <div class="flex min-h-screen items-center justify-center p-6">
            <div class="w-full max-w-md space-y-6">
                <div class="flex items-center gap-3 justify-center">
                    <x-app-logo />
                </div>

                <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 text-center">
                        <svg class="mx-auto h-12 w-12 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 3l7 4v5c0 5-3.5 9-7 9s-7-4-7-9V7l7-4z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4"/>
                        </svg>

                        <h2 class="mt-4 text-xl font-semibold">
                            {{ __('Authorize :name', ['name' => $client->name]) }}
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ __('This application will be able to use available MCP features.') }}
                        </p>
                    </div>

                    {{-- Content --}}
                    <div class="px-6 pb-6 space-y-5">
                        <div class="rounded-lg border bg-muted/50 p-4">
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                {{ __('Connected as') }}
                            </p>
                            <p class="mt-1 font-medium">
                                {{ $user->email }}
                            </p>
                        </div>

                        @if(count($scopes) > 0)
                            <div>
                                <p class="text-sm font-medium">
                                    {{ __('Requested permissions') }}
                                </p>
                                <ul class="mt-2 space-y-2">
                                    @foreach($scopes as $scope)
                                        <li class="flex items-start gap-2">
                                            <span class="mt-1 inline-block h-2 w-2 rounded-full bg-primary"></span>
                                            <span class="text-sm text-muted-foreground">
                                                {{ $scope->description }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="px-6 pb-6" x-data="{ loading: false }">
                        <div class="grid grid-cols-2 gap-3">
                            {{-- Deny --}}
                            <form method="POST" action="{{ route('passport.authorizations.deny') }}"
                                x-on:submit="loading = true" class="contents">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 w-full"
                                    x-bind:disabled="loading"
                                >
                                    <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ __('Cancel') }}
                                </button>
                            </form>

                            {{-- Approve --}}
                            <form method="POST" action="{{ route('passport.authorizations.approve') }}"
                                x-on:submit="loading = true" class="contents" id="authorizeForm">
                                @csrf
                                <input type="hidden" name="state" value="">
                                <input type="hidden" name="client_id" value="{{ $client->id }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 w-full"
                                    x-bind:disabled="loading"
                                    x-bind:class="{ 'cursor-not-allowed': loading }"
                                >
                                    <svg x-show="loading" class="-ml-1 mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span x-text="loading ? '{{ __('Authorizing...') }}' : '{{ __('Authorize') }}'"></span>
                                </button>
                            </form>
                        </div>

                        <p class="mt-4 text-xs text-center text-muted-foreground">
                            {{ __('You can revoke access at any time in your account settings.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>