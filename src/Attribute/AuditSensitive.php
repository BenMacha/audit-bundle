<?php

namespace BenMacha\AuditBundle\Attribute;

use Attribute;

/**
 * Marks a property as containing sensitive data that should be encrypted or anonymized.
 *
 * This attribute can be applied to entity properties to indicate that they contain
 * sensitive information that requires special handling in audit logs.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class AuditSensitive
{
    /**
     * @var string Strategy for handling sensitive data (encrypt, anonymize, hash, exclude)
     */
    public string $strategy;

    /**
     * @var string|null Custom encryption key or algorithm
     */
    public ?string $encryptionKey;

    /**
     * @var string|null Pattern for anonymization (e.g., '***' or 'REDACTED')
     */
    public ?string $anonymizePattern;

    /**
     * @var string|null Hash algorithm to use (e.g., 'sha256', 'md5')
     */
    public ?string $hashAlgorithm;

    /**
     * @var bool Whether to store the original value length
     */
    public bool $preserveLength;

    /**
     * @var bool Whether to store a partial value (e.g., first/last characters)
     */
    public bool $partialValue;

    /**
     * @var int Number of characters to show if partial value is enabled
     */
    public int $partialLength;

    /**
     * @var array<string> Operations for which this field should be treated as sensitive
     */
    public array $operations;

    /**
     * @var string|null Reason for marking this field as sensitive
     */
    public ?string $reason;

    /**
     * Constructor.
     *
     * @param string        $strategy         Strategy for handling sensitive data
     * @param string|null   $encryptionKey    Custom encryption key or algorithm
     * @param string|null   $anonymizePattern Pattern for anonymization
     * @param string|null   $hashAlgorithm    Hash algorithm to use
     * @param bool          $preserveLength   Whether to store the original value length
     * @param bool          $partialValue     Whether to store a partial value
     * @param int           $partialLength    Number of characters to show if partial value is enabled
     * @param array<string> $operations       Operations for which this field should be treated as sensitive
     * @param string|null   $reason           Reason for marking this field as sensitive
     */
    public function __construct(
        string $strategy = 'encrypt',
        ?string $encryptionKey = null,
        ?string $anonymizePattern = null,
        ?string $hashAlgorithm = null,
        bool $preserveLength = false,
        bool $partialValue = false,
        int $partialLength = 2,
        array $operations = ['create', 'update', 'delete'],
        ?string $reason = null
    ) {
        $this->strategy = $strategy;
        $this->encryptionKey = $encryptionKey;
        $this->anonymizePattern = $anonymizePattern;
        $this->hashAlgorithm = $hashAlgorithm;
        $this->preserveLength = $preserveLength;
        $this->partialValue = $partialValue;
        $this->partialLength = $partialLength;
        $this->operations = $operations;
        $this->reason = $reason;
    }

    /**
     * Check if this field should be treated as sensitive for a specific operation.
     *
     * @param string $operation The operation to check
     */
    public function isSensitiveForOperation(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    /**
     * Process a sensitive value according to the configured strategy.
     *
     * @param mixed $value The original value
     *
     * @return mixed The processed value
     */
    public function processValue(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        $stringValue = (string) $value;

        return match ($this->strategy) {
            'encrypt' => $this->encryptValue($stringValue),
            'anonymize' => $this->anonymizeValue($stringValue),
            'hash' => $this->hashValue($stringValue),
            'exclude' => null,
            'partial' => $this->partializeValue($stringValue),
            default => $stringValue,
        };
    }

    /**
     * Encrypt a value.
     */
    private function encryptValue(string $value): string
    {
        // Simple base64 encoding for demonstration
        // In production, use proper encryption with the configured key
        $encrypted = base64_encode($value);

        if ($this->preserveLength) {
            return sprintf('[ENCRYPTED:%d]%s', strlen($value), $encrypted);
        }

        return '[ENCRYPTED]' . $encrypted;
    }

    /**
     * Anonymize a value.
     */
    private function anonymizeValue(string $value): string
    {
        $pattern = $this->anonymizePattern ?? '***';

        if ($this->preserveLength) {
            return str_repeat('*', strlen($value));
        }

        return $pattern;
    }

    /**
     * Hash a value.
     */
    private function hashValue(string $value): string
    {
        $algorithm = $this->hashAlgorithm ?? 'sha256';
        $hash = hash($algorithm, $value);

        if ($this->preserveLength) {
            return sprintf('[HASH:%s:%d]%s', strtoupper($algorithm), strlen($value), $hash);
        }

        return sprintf('[HASH:%s]%s', strtoupper($algorithm), $hash);
    }

    /**
     * Show only partial value.
     */
    private function partializeValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= $this->partialLength * 2) {
            return str_repeat('*', $length);
        }

        $start = substr($value, 0, $this->partialLength);
        $end = substr($value, -$this->partialLength);
        $middle = str_repeat('*', $length - ($this->partialLength * 2));

        return $start . $middle . $end;
    }

    /**
     * Get the configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strategy' => $this->strategy,
            'encryptionKey' => $this->encryptionKey,
            'anonymizePattern' => $this->anonymizePattern,
            'hashAlgorithm' => $this->hashAlgorithm,
            'preserveLength' => $this->preserveLength,
            'partialValue' => $this->partialValue,
            'partialLength' => $this->partialLength,
            'operations' => $this->operations,
            'reason' => $this->reason,
        ];
    }

    /**
     * Create an instance from array configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['strategy'] ?? 'encrypt',
            $config['encryptionKey'] ?? null,
            $config['anonymizePattern'] ?? null,
            $config['hashAlgorithm'] ?? null,
            $config['preserveLength'] ?? false,
            $config['partialValue'] ?? false,
            $config['partialLength'] ?? 2,
            $config['operations'] ?? ['create', 'update', 'delete'],
            $config['reason'] ?? null
        );
    }
}
