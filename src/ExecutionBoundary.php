<?php

declare(strict_types=1);

namespace Robo\AuthSdk;

use InvalidArgumentException;

/** Signed transport projection, never a replacement for canonical domain decisions. */
final readonly class ExecutionBoundary
{
    public const SCHEMA = 'execution-boundary.v1';
    // Old receivers reject this result-contract selector before any provider dispatch.
    public const RESULT_CONTRACT = 'flowbeacon.execution-result.v1+execution-boundary.v1';
    public const RECEIPT_SCHEMA = 'execution-boundary-receipt.v1';

    private function __construct(private array $value) {}

    public static function fromArray(array $value): self
    {
        self::keys($value, 'schema id policy_version decision_refs evaluated_at valid_until deadline operations resources effects review_policy data_egress limits expected_output_schema');
        if ($value['schema'] !== self::SCHEMA || $value['operations'] !== ['generate_proposal']
            || $value['effects'] !== [] || $value['review_policy'] !== 'human_review.required') self::invalid();
        foreach (['id', 'policy_version', 'expected_output_schema'] as $key) self::identifier($value[$key]);
        $refs = $value['decision_refs'];
        if (!is_array($refs) || !array_is_list($refs) || count($refs) < 1 || count($refs) > 32) self::invalid();
        foreach ($refs as $ref) self::identifier($ref);
        if (count(array_unique($refs)) !== count($refs)) self::invalid();
        foreach (['evaluated_at', 'valid_until', 'deadline'] as $field) {
            if (!is_int($value[$field]) || $value[$field] < 1 || $value[$field] > 253402300799) self::invalid();
        }
        if (!($value['evaluated_at'] < $value['deadline'] && $value['deadline'] <= $value['valid_until']
            && $value['valid_until'] <= $value['evaluated_at'] + 300)) self::invalid();
        if (!is_array($value['resources']) || !array_is_list($value['resources']) || count($value['resources']) !== 1) self::invalid();
        $source = $value['resources'][0];
        self::keys($source, 'type ref version digest');
        foreach (['type', 'ref', 'version'] as $key) self::identifier($source[$key]);
        self::digest($source['digest']);
        $egress = $value['data_egress'];
        self::keys($egress, 'provider_ids input_sha256 store');
        if (!is_array($egress['provider_ids']) || !array_is_list($egress['provider_ids'])
            || count($egress['provider_ids']) < 1 || count($egress['provider_ids']) > 8 || $egress['store'] !== false) self::invalid();
        foreach ($egress['provider_ids'] as $provider) self::identifier($provider);
        if (count(array_unique($egress['provider_ids'])) !== count($egress['provider_ids'])) self::invalid();
        self::digest($egress['input_sha256']);
        $limits = $value['limits'];
        self::keys($limits, 'provider_calls max_output_tokens max_input_bytes');
        if ($limits['provider_calls'] !== 1) self::invalid();
        foreach (['max_output_tokens' => 32768, 'max_input_bytes' => 524288] as $field => $maximum) {
            if (!is_int($limits[$field]) || $limits[$field] < 1 || $limits[$field] > $maximum) self::invalid();
        }
        return new self($value);
    }

    /** Validate before signing; this does not authorize issuance or renew validity. */
    public function assertMatches(array $claims, ExecutionInput $input): void
    {
        $this->assertClaims($claims);
        $encoded = self::canonicalJson($input->toArray());
        if (!hash_equals($this->value['data_egress']['input_sha256'], hash('sha256', $encoded))
            || strlen($encoded) > $this->value['limits']['max_input_bytes']) self::invalid();
    }

    public function assertClaims(array $claims): void
    {
        $source = $claims['source'] ?? null;
        if (!is_array($source) || self::canonicalJson($source) !== self::canonicalJson($this->value['resources'][0])
            || !is_array($claims['decision_refs'] ?? null)
            || array_diff($this->value['decision_refs'], $claims['decision_refs']) !== []) self::invalid();
    }

    public function assertReceipt(array $receipt): void
    {
        self::keys($receipt, 'schema id policy_version digest');
        if ($receipt['schema'] !== self::RECEIPT_SCHEMA || $receipt['id'] !== $this->value['id']
            || $receipt['policy_version'] !== $this->value['policy_version']
            || !is_string($receipt['digest']) || !hash_equals($this->digestValue(), $receipt['digest'])) self::invalid();
    }

    public function toArray(): array { return $this->value; }
    public function digestValue(): string { return hash('sha256', self::canonicalJson($this->value)); }

    /** Wire canonicalization: sorted object keys, original array order, unescaped UTF-8/slashes. */
    public static function canonicalJson(array $value): string
    {
        $normalize = static function (array $item) use (&$normalize): array {
            if (!array_is_list($item)) ksort($item, SORT_STRING);
            foreach ($item as &$child) if (is_array($child)) $child = $normalize($child);
            return $item;
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    private static function keys(mixed $value, string $expected): void
    {
        if (!is_array($value)) self::invalid();
        $keys = array_keys($value); sort($keys, SORT_STRING);
        $wanted = explode(' ', $expected); sort($wanted, SORT_STRING);
        if ($keys !== $wanted) self::invalid();
    }
    private static function identifier(mixed $value): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 255 || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $value)) self::invalid();
    }
    private static function digest(mixed $value): void
    {
        if (!is_string($value) || preg_match('/^[0-9a-f]{64}$/D', $value) !== 1) self::invalid();
    }
    private static function invalid(): never { throw new InvalidArgumentException('Invalid execution boundary or mismatched evidence.'); }
}
