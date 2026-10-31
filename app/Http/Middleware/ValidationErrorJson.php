<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidationErrorJson
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (isset($response->exception) && $response->exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Bad request',
                'errors' => $response->exception->errors()
            ], 400);
        }

        // NOT USE ( OTHERWISE WILL SHOW ERRORS ON PRODUCTION )
        /*if (isset($response->exception)) {*/
        /*    return response()->json([*/
        /*        'success' => false,*/
        /*        'message' => 'Internal error',*/
        /*        'error' => $response->exception->getMessage()*/
        /*    ], 400);*/
        /*}*/

        return $response;
    }
}
