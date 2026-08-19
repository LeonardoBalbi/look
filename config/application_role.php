<?php

return [
    /*
     * store: aplicação operacional instalada para uma única empresa.
     * license_server: portal central de licenças em domínio separado.
     * combined: disponível somente para testes e desenvolvimento legado.
     */
    'role' => env('APP_ROLE', 'store'),
];
