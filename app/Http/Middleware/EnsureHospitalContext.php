<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class EnsureHospitalContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isActiveUser()) {
            abort(403, 'Akun tidak aktif. Hubungi administrator casemix.');
        }

        /** @var Hospital|null $hospital */
        $hospital = $user->hospital;

        if ($hospital === null) {
            abort(403, 'Akun belum terhubung ke rumah sakit mana pun.');
        }

        Context::add('hospital_id', $hospital->id);
        Context::add('hospital_code', $hospital->kode_rs);

        return $next($request);
    }
}
