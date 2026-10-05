<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Robo\\AuthSdk\\';
    if (str_starts_with($class, $prefix)) require_once __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
});

use Robo\AuthSdk\{ExecutionBoundary, ExecutionInput, ExecutionIntentContext, ExecutionResult};

function checkBoundary(bool $condition): void { if (!$condition) throw new RuntimeException('Boundary contract assertion failed.'); }
function rejectBoundary(callable $parse): void {
    try { $parse(); } catch (InvalidArgumentException $expected) { return; }
    throw new RuntimeException('Invalid boundary accepted.');
}
$f = json_decode(file_get_contents(__DIR__ . '/Fixtures/execution-boundary-v1.json'), true, 512, JSON_THROW_ON_ERROR);
$b = ExecutionBoundary::fromArray($f['boundary']);
checkBoundary($b->toArray() === $f['boundary']);
$b->assertMatches($f['claims'], ExecutionInput::fromArray($f['input']));
$b->assertReceipt($f['receipt']);
checkBoundary($b->digestValue() === $f['receipt']['digest']);
$claims = $f['claims'] + ['contract_version'=>'2', 'issued_at'=>1791200000, 'expires_at'=>1791200300];
checkBoundary(ExecutionIntentContext::fromClaims($claims)->boundary?->toArray() === $f['boundary']);
unset($claims['execution_boundary']);
checkBoundary(ExecutionIntentContext::fromClaims($claims)->boundary === null);
foreach ([['operations',['apply']], ['effects',['write']], ['review_policy','none'], ['schema','future'], ['decision_refs',[]], ['deadline',true], ['valid_until',1791200301], ['tools',['arbitrary']]] as [$key,$value]) {
    $bad=$f['boundary']; $bad[$key]=$value;
    rejectBoundary(fn()=>ExecutionBoundary::fromArray($bad));
}
foreach ([['provider_calls',2],['provider_calls',true],['max_output_tokens',true],['max_input_bytes',0]] as [$key,$value]) {
    $bad=$f['boundary']; $bad['limits'][$key]=$value;
    rejectBoundary(fn()=>ExecutionBoundary::fromArray($bad));
}
$bad=$f['boundary'];$bad['data_egress']['store']=true;rejectBoundary(fn()=>ExecutionBoundary::fromArray($bad));
$bad=$f['boundary'];$bad['id']="\xff";rejectBoundary(fn()=>ExecutionBoundary::fromArray($bad));
$input=$f['input'];$input['messages'][1]['content'].=' expand scope';
rejectBoundary(fn()=>$b->assertMatches($f['claims'],ExecutionInput::fromArray($input)));
$claims=$f['claims'];$claims['source']['ref']='another-source';
rejectBoundary(fn()=>$b->assertMatches($claims,ExecutionInput::fromArray($f['input'])));
$receipt=$f['receipt'];$receipt['digest']=str_repeat('0',64);rejectBoundary(fn()=>$b->assertReceipt($receipt));
$wire=['schema'=>ExecutionResult::SCHEMA, 'correlation'=>['caller_invocation_id'=>'invocation-1','caller_intent_id'=>'intent-1','correlation_id'=>'correlation-1','status'=>'started','flowbeacon_execution_id'=>'remote-1'], 'output'=>null,'error'=>null,'artifacts'=>[], 'boundary'=>$f['receipt']];
$result=ExecutionResult::fromArray($wire);checkBoundary($result->boundary === $f['receipt']);$b->assertReceipt($result->boundary);
checkBoundary($result->toArray()['boundary'] === $f['receipt']);
$wire['boundary']=null;rejectBoundary(fn()=>ExecutionResult::fromArray($wire));
unset($wire['boundary']);checkBoundary(!array_key_exists('boundary',ExecutionResult::fromArray($wire)->toArray()));
echo "execution boundary contract: PASS\n";
