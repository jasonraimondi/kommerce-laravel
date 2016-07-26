<?php

Route::get('/', function () {
    return redirect()->route('api.products.index');
});

Route::group(['prefix' => 'api', 'middleware' => ['cors']], function() {
    Route::resource('products', 'ProductController');
});

Route::get('/createDummyProduct', [
    'as' => 'p.createDummy',
    'uses' => 'ProductController@createDummy'
]);
