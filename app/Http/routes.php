<?php

Route::get('/', function () {
    return view('default');
});

Route::get('/api/v1/{model}/{action}/{method}', [
    'as' => 'a.processQuery',
    'uses' => 'ApiController@processQuery'
])->where([
    'action' => '.*Query'
]);

Route::get('/api/v1/{model}/{action}/{method?}', [
    'as' => 'a.processCommand',
    'uses' => 'ApiController@processCommand'
])->where([
    'action' => '.*Command'
]);

Route::get('/dummyData/createDummyProduct', [
    'as' => 'ddc.createDummyProduct',
    'uses' => 'DummyDataController@createDummyProduct'
]);

Route::group(['middleware' => ['render']], function () {
    Route::get('/p/{slug}-{productId}', 'ProductController@show')
        ->name('product/show')
        ->where([
            'slug' => '[a-z0-9-]+',
            'productId' => '[0-9a-f]{32}',
        ]);
});
