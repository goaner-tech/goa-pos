<?php

use Illuminate\Support\Facades\Route;

// The terminal app is a Vue single-page app; every screen path serves the same shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
