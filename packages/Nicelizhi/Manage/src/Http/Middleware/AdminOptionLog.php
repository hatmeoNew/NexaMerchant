<?php

namespace Nicelizhi\Manage\Http\Middleware;

use Carbon\Carbon;
use Closure;

class AdminOptionLog
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $now = Carbon::now(); // 使用Carbon对象
        $user_id = auth()->guard('admin')->user()->id;
        $log = [
            'user_id' => $user_id,
            'path'    => substr($request->path(), 0, 255),
            'method'  => $request->method(),
            'ip'      => $request->getClientIp(),
            'input'   => json_encode($request->input()),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            \Nicelizhi\Manage\Models\AdminOperationLog::create($log);
        } catch (\Exception $exception) {
            // pass
        }
        return $next($request);
    }
}
