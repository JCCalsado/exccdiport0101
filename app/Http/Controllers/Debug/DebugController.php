<?php

namespace App\Http\Controllers\Debug;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DebugController
{
    public function csrfToken(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
            'session_token' => $request->session()->token(),
            'session_exists' => session()->exists('_token'),
            'session_id' => session()->getId(),
            'x_csrf_header' => $request->header('X-CSRF-TOKEN'),
            'x_token_header' => $request->header('X-Token'),
            'request_token' => $request->get('_token'),
        ]);
    }
}
