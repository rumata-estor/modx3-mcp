<?php
/**
 * Offline Ed25519 permit verifier interoperability/negative tests.
 * No MODX bootstrap; no site settings, secrets or nonce ledger.
 */
require_once __DIR__ . '/../core/components/modxmcp/src/Security/IndependentPermitV1.php';
use ModxMcp\Security\IndependentPermitV1 as V;
$input = json_decode(stream_get_contents(STDIN),true);
if (!is_array($input) || !isset($input['claims'],$input['signature'],$input['public_key'],
    $input['request_digest'],$input['trusted'],$input['now'])) {
    throw new RuntimeException('Malformed offline fixture');
}
$claims=$input['claims'];
$sig=$input['signature'];
$pub=$input['public_key'];
$digest=$input['request_digest'];
$trusted=$input['trusted'];
$now=$input['now'];
$checks=0;
function permitMustReject($fn,$reason) {
    global $checks;
    try {$fn();}catch(\Throwable $e){$checks++;return;}
    throw new RuntimeException('Permit verifier incorrectly accepted '.$reason);
}
if (!V::verifyCryptographicClaims($claims,$sig,$pub,$trusted,$digest,$now)) {
    throw new RuntimeException('Cross-language Ed25519 permit not verified');
}
$checks++;
$mutation=$claims;
$mutation['site_id']='other-site';
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'tampered site claim');
$mutation=$claims;$mutation['action']='delete_user';
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'tampered action');
$mutation=$claims;$mutation['element_type']='snippet';
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'tampered type');
$mutation=$claims;$mutation['nonce']=str_repeat('0',32);
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'tampered nonce');
$mutation=$claims;$mutation['request_digest']=str_repeat('a',64);
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'tampered signed digest');
permitMustReject(function()use($claims,$sig,$pub,$trusted,$now){
    V::verifyCryptographicClaims($claims,$sig,$pub,$trusted,str_repeat('0',64),$now);
},'altered request bytes');
permitMustReject(function()use($claims,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($claims,$sig,$pub,$trusted,$digest,$now+121);
},'expired permit');
permitMustReject(function()use($claims,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($claims,$sig,$pub,$trusted,$digest,$now-121);
},'premature future permit');
permitMustReject(function()use($claims,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($claims, str_repeat('0',128),$pub,$trusted,$digest,$now);
},'forged signature');
permitMustReject(function()use($claims,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($claims,$sig,str_repeat('0',64),$trusted,$digest,$now);
},'wrong public key');
$mutation=$claims;$mutation['random_extra']='ignored';
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'extra claim');
$mutation=$claims;unset($mutation['nonce']);
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'missing claim');
$mutation=$claims;$mutation['version']='2';
permitMustReject(function()use($mutation,$sig,$pub,$trusted,$digest,$now){
    V::verifyCryptographicClaims($mutation,$sig,$pub,$trusted,$digest,$now);
},'downgraded/unknown permit version');
if ($checks !== 14) throw new RuntimeException('Negative test count mismatch');
echo 'ED25519_INDEPENDENT_PERMIT_CRYPTO_OK checks='.$checks
    .' (REPLAY_LEDGER_NOT_YET_IMPLEMENTED)' . PHP_EOL;
