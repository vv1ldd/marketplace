<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OutOrder extends Controller
{
    public function create(Request $request, string $connection)
    {
        $log = \Log::channel("{$connection}_notification")->withContext([
            'log_id' => Str::random(8),
        ]);

        $log->info('-------------');

        $log->debug('Пришло уведомление', [
            'connection' => $connection,
            'body' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip()
        ]);


    }
}
