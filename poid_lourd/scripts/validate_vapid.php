<?php
require __DIR__ . '/../includes/fcm_vapid_validate.php';
$k = 'BAz5H7zdBJiKJSkTjMWb5e6OgPyNUKzfoTThWQv66tmxcaDY5rvVxNljgBvrrE85svwT8pgjYR46k8iH3Uiew3c';
print_r(fcm_validate_vapid_key($k));
