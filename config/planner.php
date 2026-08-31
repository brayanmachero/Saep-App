<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plan operativo de SAEP en Microsoft Planner
    |--------------------------------------------------------------------------
    |
    | El identificador se mantiene fuera del código para que la integración
    | nunca cree o modifique tareas de otro plan de Microsoft 365.
    |
    */
    'plan_id' => env('MSGRAPH_PLANNER_PLAN_ID'),
];
