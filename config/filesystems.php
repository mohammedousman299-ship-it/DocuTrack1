<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         | Stockage objet des images de documents (§3.3).
         |
         | Les variables sont préfixées DOCUTRACK_S3_ et NON AWS_ : de
         | nombreux environnements définissent déjà AWS_ACCESS_KEY_ID et
         | AWS_SECRET_ACCESS_KEY au niveau du système, et ces variables
         | d'environnement priment sur le fichier .env. La configuration du
         | projet se retrouvait alors silencieusement remplacée par des
         | identifiants étrangers, avec une erreur de stockage incompréhensible
         | à l'arrivée. Un nom propre au projet supprime cette classe de bug.
         */
        's3' => [
            'driver' => 's3',
            'key' => env('DOCUTRACK_S3_KEY'),
            'secret' => env('DOCUTRACK_S3_SECRET'),
            'region' => env('DOCUTRACK_S3_REGION', 'us-east-1'),
            'bucket' => env('DOCUTRACK_S3_BUCKET'),
            'url' => env('DOCUTRACK_S3_URL'),
            'endpoint' => env('DOCUTRACK_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('DOCUTRACK_S3_PATH_STYLE', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
