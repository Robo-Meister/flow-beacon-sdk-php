<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ExecutorResultCorrelation.php';
require_once __DIR__ . '/../src/ExecutionResult.php';

use Robo\AuthSdk\ExecutionResult;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rejects(array $value): void {
    try { ExecutionResult::fromArray($value); } catch (InvalidArgumentException|JsonException $expected) { return; }
    throw new RuntimeException('Malformed result was accepted.');
}
$fixtures = glob(__DIR__ . '/Fixtures/ExecutionResult/*.json');
check(count($fixtures) === 3, 'All three action fixtures are required.');
foreach ($fixtures as $file) {
    $wire = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $result = ExecutionResult::fromArray($wire);
    check($result->isCompleted() && !$result->isPending(), 'Completed result misclassified.');
    check($result->outputData === $wire['output']['data'], 'Output changed in SDK.');
    check($result->outputSchema === $wire['output']['schema'], 'Schema changed in SDK.');
    check(ExecutionResult::fromArray($result->toArray())->outputData === $result->outputData, 'Round trip changed output.');
    $pending = $wire; $pending['correlation']['status'] = 'started'; $pending['output'] = null;
    check(ExecutionResult::fromArray($pending)->isPending(), 'Started must remain pending.');
    $failed = $pending; $failed['correlation']['status'] = 'failed';
    $failed['error'] = ['code'=>'OUTPUT_SCHEMA_INVALID', 'category'=>'output_contract', 'retryable'=>false];
    check(ExecutionResult::fromArray($failed)->isFailed(), 'Failure misclassified.');
    $bad = $wire; $bad['schema'] = 'future'; rejects($bad);
    $bad = $wire; $bad['output']['data']['schema'] = 'wrong'; rejects($bad);
    $bad = $wire; $bad['correlation']['status'] = 'started'; rejects($bad);
    $bad = $wire; $bad['output'] = null; rejects($bad);
    $bad = $wire; unset($bad['correlation']['flowbeacon_execution_id']); rejects($bad);
    $bad = $wire; $bad['correlation']['flowbeacon_execution_id'] = $bad['correlation']['caller_invocation_id']; rejects($bad);
    $bad = $wire; $bad['error'] = ['code'=>'failure']; rejects($bad);
}
echo "Execution result SDK contract: PASS (3 wire fixtures, round trips and invalid states).\n";
