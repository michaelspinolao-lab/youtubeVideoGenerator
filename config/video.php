<?php

return [
    'allowed_email' => env('ALLOWED_LOGIN_EMAIL'),
    'python' => env('VIDEO_PYTHON', 'python3'),
    'gemini_key' => env('GEMINI_API_KEY'),
    'gemini_model' => env('GEMINI_MODEL'),
    'pixabay_key' => env('PIXABAY_API_KEY'),
];
