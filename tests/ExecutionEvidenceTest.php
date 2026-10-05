<?php

declare(strict_types=1);

namespace Robo\AuthSdk\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Robo\AuthSdk\ExecutionEvidence;

final class ExecutionEvidenceTest extends TestCase
{
    public function testOpenAiAndAnthropicEvidenceRoundTrip(): void
    {
        $evidence = $this->evidence();
        self::assertSame($evidence, ExecutionEvidence::fromArray($evidence)->toArray());
        $evidence['profile']['provider'] = 'anthropic';
        $evidence['profile']['api'] = 'messages';
        $evidence['profile']['model'] = 'claude-sonnet-4-5-20250929';
        $evidence['attempt']['model'] = $evidence['profile']['model'];
        $evidence['attempt']['response_id'] = 'msg_synthetic';
        self::assertSame($evidence, ExecutionEvidence::fromArray($evidence)->toArray());
    }

    public function testMismatchedOrUnknownProviderApisAreRejected(): void
    {
        foreach ([['anthropic', 'responses'], ['openai', 'messages'], ['other', 'messages'], [[], 'messages']] as [$provider, $api]) {
            $evidence = $this->evidence();
            $evidence['profile']['provider'] = $provider;
            $evidence['profile']['api'] = $api;
            try {
                ExecutionEvidence::fromArray($evidence);
                self::fail('Invalid provider/API pair accepted.');
            } catch (InvalidArgumentException $expected) {
                self::assertSame('Invalid execution profile.', $expected->getMessage());
            }
        }
    }

    private function evidence(): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__ . '/Fixtures/execution-evidence-v1.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );
    }
}
