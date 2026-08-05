<?php

return [
    /*
     * Minimum driver age per vehicle category, evaluated against the
     * booking's pickup date (not "today"). A category not listed here has
     * no minimum age requirement.
     */
    'minimum_age_by_category' => [
        'economy' => 21,
        'suv' => 21,
        'van' => 21,
        'luxury' => 25,
    ],
];
