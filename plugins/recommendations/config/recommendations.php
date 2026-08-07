<?php

return [
    /*
     * Which strategy the vehicle.recommendations filter uses to find
     * similar vehicles. Valid values:
     *   - 'same_category'  — available vehicles in the same category (default)
     *   - 'similar_price'  — available vehicles within ±30% of this one's daily rate
     */
    'active_strategy' => 'same_category',

    /*
     * Maximum number of recommendation cards rendered on the detail page.
     */
    'max_results' => 4,
];
