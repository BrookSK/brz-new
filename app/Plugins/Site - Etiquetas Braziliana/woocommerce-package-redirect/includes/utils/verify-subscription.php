<?php

function verify_subscription() {
    $subscriptions = wcs_get_users_subscriptions();
    
    foreach ($subscriptions as $subscription) {
        if ($subscription->has_status('active')) {
            return true;
        }
    }
    
    return false;
}