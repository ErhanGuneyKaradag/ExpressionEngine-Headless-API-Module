<?php

return array(
    'author'         => 'Güney Karadağ',
    'author_url'     => 'https://github.com/ErhanGuneyKaradag',
    'name'           => 'API Module',
    'description'    => 'Headless API for channel entries with token authentication',
    'version'        => '1.0.0',
    'namespace'      => 'Guney\ApiModule',
    'settings_exist' => FALSE,
    'models' => array(
        'ApiToken' => 'Model\ApiToken'
    )
);