<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthTokenService;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class Protector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $role)
    {

        $supportedRoles = Config::get('protector.roles');

        if(!in_array($role, $supportedRoles)){
            throw new Exception("Unknown user authentication role");
        }

        $authHeader = $request->header('Authorization');

        if (trim($authHeader ?? "") === "") {
            return response()->json(["message" => "Unauthenticated"], Response::HTTP_UNAUTHORIZED);
        }

        $authHeader = explode(" ", $authHeader)[1];

        $validation = (new AuthTokenService())->isTokenValid($authHeader);

        if ($validation === false) {
            return response()->json(["message" => "Unauthenticated"], Response::HTTP_UNAUTHORIZED);
        }

        $userUuid = $validation->claims()->get('uid');

        $token = User::where("uuid", $userUuid)->first()->tokens[0];

        if (Carbon::parse($token->expires_at) < now()) {
            return response()->json(["message" => "Token expired"], Response::HTTP_UNAUTHORIZED);
        }

        if($role === "admin" && $token->user->is_admin === 0){
            return response()->json(["message" => "You don't have enough permissions"], Response::HTTP_FORBIDDEN);
        }
        

        return $next($request);
    }
}
