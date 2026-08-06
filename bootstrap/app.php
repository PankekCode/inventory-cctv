<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

    $middleware->redirectGuestsTo(fn ($request) => null);
    $middleware->alias([
        'admin' => EnsureAdmin::class,
    ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            \Throwable $e,
            $request
        ) {

            if ($request->expectsJson() && $request->is('api/*')) {

                if ($e instanceof ValidationException) {

                    return response()->json([
                        'message' => 'Data tidak valid.',
                        'errors' => $e->errors()
                    ], 422);

                }

                if ($e instanceof InsufficientStockException) {

                    return response()->json([
                        'message' => $e->getMessage(),
                        'errors' => [
                            'stock' => [
                                'Stock tidak mencukupi.'
                            ]
                        ]
                    ], 422);

                }

                if ($e instanceof ModelNotFoundException) {
                    return response()->json([
                        'message' => 'Data tidak ditemukan.',
                    ], 404);
                }

                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'message' => 'Autentikasi diperlukan.',
                    ], 401);
                }

                if ($e instanceof AuthorizationException) {
                    return response()->json([
                        'message' => 'Anda tidak memiliki akses untuk tindakan ini.',
                    ], 403);
                }

                if ($e instanceof HttpExceptionInterface) {
                    return response()->json([
                        'message' => $e->getStatusCode() >= 500
                            ? 'Terjadi kesalahan pada server.'
                            : ($e->getMessage() ?: 'Permintaan tidak dapat diproses.'),
                    ], $e->getStatusCode());
                }

                return response()->json([
                    'message' => config('app.debug')
                        ? $e->getMessage()
                        : 'Terjadi kesalahan pada server.',
                    'errors' => null
                ], 500);

            }

        });

    })
    ->create();
