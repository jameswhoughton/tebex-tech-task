<?php

use App\Exceptions\ExternalRequestFailedException;
use App\Http\Resources\ErrorResource;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleWithRedis();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {

            if ($request->is('api/*')) {
                return new ErrorResource([
                    'message' => 'Not found',
                ])->response()->setStatusCode(Response::HTTP_NOT_FOUND);
            }
        });

        $exceptions->render(function (ExternalRequestFailedException $e) {
            $code = $e->getStatusCode();

            // Specifically handle not found errors
            if ($code === Response::HTTP_NOT_FOUND) {
                return new ErrorResource([
                    'message' => $e->getMessage(),
                    'details' => [
                        'external_response_code' => $code,
                    ],
                ])->response()->setStatusCode(Response::HTTP_NOT_FOUND);
            }

            // Any server errors form the external call can be treated as a 502
            if ($code >= 500) {
                return new ErrorResource([
                    'message' => $e->getMessage(),
                    'details' => [
                        'external_response_code' => $code,
                    ],
                ])->response()->setStatusCode(Response::HTTP_BAD_GATEWAY);
            }

            // Any other client errors can be treated as a 400
            if ($code >= 400) {
                return new ErrorResource([
                    'message' => $e->getMessage(),
                    'details' => [
                        'external_response_code' => $code,
                    ],
                ])->response()->setStatusCode(Response::HTTP_BAD_REQUEST);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e) {
            return new ErrorResource([
                'message' => $e->getMessage(),
            ])->response()->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS);
        });
    })->create();
