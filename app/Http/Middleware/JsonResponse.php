<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class JsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        Log::channel('request')->info(sprintf('(%s) %s %s', $request->method(), $request->url(), Auth::id()), parameterReplace($request->all() + (Route::current()?->parameters() ?? [])));

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $response->header('Charset', 'UTF-8');
            $response->header('Content-Type','application/json; charset=UTF-8');
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        }

        Log::channel('response')->info(sprintf('(%s) %s %s', $request->method(), $request->url(), Auth::id()), (array)$response->getOriginalContent());
        return $response;
    }
}
