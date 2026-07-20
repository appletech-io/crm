<?php

use App\Http\Middleware\SetActiveIndustry;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('auth', SetActiveIndustry::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Filament's Authenticate middleware aborts with a 403 when a user can't
        // access the current panel (User::canAccessPanel()). Rather than showing
        // a dead-end 403 page, send the user to whichever panel they *can* use.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            $user = $request->user();

            if (! $user) {
                return null;
            }

            // Built explicitly rather than via the redirect() helper: when this 403
            // originates from inside a Livewire full-page component (e.g. a Filament
            // resource's canViewAny() check), Livewire rebinds redirect() to its own
            // Redirector, which isn't a valid HTTP response and breaks middleware
            // further down the pipeline.
            $redirectTo = match (true) {
                $user->hasRole('candidate') => '/candidate',
                $user->hasRole('client') => '/client',
                default => '/crm',
            };

            return new RedirectResponse($redirectTo);
        });
    })->create();
