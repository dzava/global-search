<?php

return [
    /*
     * The method used to format the search results. If the method does not
     * exist then toArray is used
     */
    'toArray' => 'toArray',

    /*
     * The name of the request parameter to search for
     */
    'q' => 'q',

    /*
     * Name of the policy that determines if the user can search for records
     * of a model
     */
    'policy-method' => 'search',

    /*
     * The method used to override the search query
     */
    'searchQuery' => 'searchQuery',
];
