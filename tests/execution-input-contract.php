<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ExecutionInput.php';
require_once __DIR__ . '/../src/ExecutionEvidence.php';
require_once __DIR__ . '/../src/ExecutorResultCorrelation.php';
require_once __DIR__ . '/../src/ExecutionResult.php';

use Robo\AuthSdk\{ExecutionInput, ExecutionEvidence, ExecutionResult};

function checkInput(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rejectInput(callable $parse): void
{
    try { $parse(); } catch (InvalidArgumentException $expected) { return; }
    throw new RuntimeException('Malformed contract accepted.');
}
$fixtures = json_decode(file_get_contents(__DIR__ . '/Fixtures/execution-input-v1.json'), true, 512, JSON_THROW_ON_ERROR);
checkInput(count($fixtures) === 4, 'Four technical profiles required.');
foreach ($fixtures as $fixture) {
    $wire = $fixture['input'];
    checkInput(ExecutionInput::fromArray($wire)->toArray() === $wire, 'Input changed in round trip.');
    $bad = $wire; $bad['messages'][1]['role'] = 'system'; rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['messages'][0]['content'] = ''; rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['messages'][0]['content'] = str_repeat('x', 262145); rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['schema'] = 'future'; rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['provider'] = 'other'; rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['messages'][1]['tool_calls'] = []; rejectInput(fn () => ExecutionInput::fromArray($bad));
    $bad = $wire; $bad['messages'][1]['content'] = "\xff"; rejectInput(fn () => ExecutionInput::fromArray($bad));
}
$evidence = json_decode(file_get_contents(__DIR__ . '/Fixtures/execution-evidence-v1.json'), true, 512, JSON_THROW_ON_ERROR);
checkInput(ExecutionEvidence::fromArray($evidence)->toArray() === $evidence, 'Evidence changed in round trip.');
$bad = $evidence; $bad['attempt']['credential'] = 'must-not-pass'; rejectInput(fn () => ExecutionEvidence::fromArray($bad));
$bad = $evidence; $bad['attempt']['usage']['total_tokens'] = -1; rejectInput(fn () => ExecutionEvidence::fromArray($bad));
$bad = $evidence; $bad['profile']['store'] = true; rejectInput(fn () => ExecutionEvidence::fromArray($bad));
$result = json_decode(file_get_contents(__DIR__ . '/Fixtures/ExecutionResult/execution_brief_assist_v1.json'), true, 512, JSON_THROW_ON_ERROR);
checkInput(!array_key_exists('execution', ExecutionResult::fromArray($result)->toArray()), 'Historical result changed.');
$result['execution'] = $evidence;
checkInput(ExecutionResult::fromArray($result)->toArray()['execution'] === $evidence, 'Result lost evidence.');
echo "execution-input/evidence contracts: PASS (four profiles, legacy, malformed inputs)\n";
