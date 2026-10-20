<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

    /*
    |--------------------------------------------------------------------------
    | Blade View Checking
    |--------------------------------------------------------------------------
    |
    | When set to true, Blade views will be checked for changes on each
    | request, which is useful during development. However, this check
    | can be expensive, so it should be disabled in production.
    |
    */

    'cache' => env('VIEW_CACHE', true),

    /*
    |--------------------------------------------------------------------------
    | Compiled View Extension
    |--------------------------------------------------------------------------
    |
    | This option determines the file extension used for compiled Blade
    | templates. By default, this is set to "php" which is the standard
    | extension for compiled PHP files.
    |
    */

    'compiled_extension' => 'php',

    /*
    |--------------------------------------------------------------------------
    | Relative Hash
    |--------------------------------------------------------------------------
    |
    | When set to true, Blade will use a relative hash for compiled view
    | paths. This is useful when deploying to multiple servers or when
    | using a shared storage directory.
    |
    */

    'relative_hash' => false,

];

