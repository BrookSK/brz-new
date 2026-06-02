<?php

function get_user_by_suite($suite) {
    if (!$suite) {
        return null;
    }
    $user_query = new WP_User_Query(array(
        'meta_key' => 'suite',
        'meta_value' => $suite
    ));
    $users = $user_query->get_results();
    if (empty($users)) {
        return null;
    }
    return $users[0];
}