<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
(new \Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
(new \Illuminate\Foundation\Bootstrap\HandleExceptions)->bootstrap($app);

echo \App\Models\StudentPaymentTerm::where('user_id', 3)->count();
