<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/test-mail', function () {
    Mail::raw('✅ Test email from Laravel SMTP working fine!', function ($message) {
        $message->to('shyakas83@gmail.com')
            ->subject('Laravel SMTP Test');
    });

    return "Mail Sent ✅";
});
