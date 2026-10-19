<?php

return [

    /*
    | The venue's main currency. All prices are stored as integer minor units
    | (1 SSP = 100 piasters). US dollar cash handling arrives with payments.
    */
    'currency' => env('POS_CURRENCY', 'SSP'),

    'venue_name' => env('POS_VENUE_NAME', 'GOA POS'),

];
