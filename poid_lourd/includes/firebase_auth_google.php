<?php
/**
 * Rétrocompatibilité — utilise firebase_auth_token.php
 */
require_once __DIR__ . '/firebase_auth_token.php';

function firebase_auth_google_error($message)
{
    return firebase_auth_token_error($message);
}

function firebase_auth_google_verify_id_token($id_token)
{
    $result = firebase_auth_verify_id_token($id_token, 'google.com');
    return $result;
}

function firebase_auth_google_profile_from_claims(array $claims)
{
    return firebase_auth_profile_from_claims($claims);
}
?>
