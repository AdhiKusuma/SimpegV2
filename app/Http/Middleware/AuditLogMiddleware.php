<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->auditLogs->recordRequest($request, $response);

        return $response;
    }
}
