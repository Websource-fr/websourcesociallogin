<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Flow;

use Websource\SocialLogin\Security\Token;

/** Implémentation en mémoire (tests). */
final class MemoryFlowStore implements FlowStoreInterface
{
    /** @var array<string,array<string,mixed>> */
    public $rows = [];
    /** @var int */
    public $now;

    public function __construct(?int $now = null)
    {
        $this->now = $now ?? time();
    }

    public function create(string $type, array $payload, int $ttl, string $bindHash, string $ipHash): string
    {
        $token = Token::random(32);
        $this->rows[Token::hash($token)] = ['type' => $type, 'payload' => $payload, 'bind' => $bindHash, 'ip' => $ipHash, 'expires' => $this->now + $ttl, 'created' => $this->now];

        return $token;
    }

    private function row(string $type, string $token, string $bindHash): ?string
    {
        $h = Token::hash($token);
        $r = $this->rows[$h] ?? null;
        if ($r === null || $r['type'] !== $type || $r['expires'] < $this->now || !hash_equals((string) $r['bind'], $bindHash)) {
            return null;
        }

        return $h;
    }

    public function find(string $type, string $token, string $bindHash): ?array
    {
        $h = $this->row($type, $token, $bindHash);

        return $h === null ? null : $this->rows[$h]['payload'];
    }

    public function update(string $type, string $token, array $payload): void
    {
        $h = Token::hash($token);
        if (isset($this->rows[$h])) {
            $this->rows[$h]['payload'] = $payload;
        }
    }

    public function consume(string $type, string $token, string $bindHash): ?array
    {
        $h = $this->row($type, $token, $bindHash);
        if ($h === null) {
            return null;
        }
        $p = $this->rows[$h]['payload'];
        unset($this->rows[$h]);

        return $p;
    }

    public function delete(string $type, string $token): void
    {
        unset($this->rows[Token::hash($token)]);
    }

    public function countRecent(string $ipHash, int $seconds): int
    {
        $n = 0;
        foreach ($this->rows as $r) {
            if ($r['ip'] === $ipHash && $r['created'] >= $this->now - $seconds) {
                ++$n;
            }
        }

        return $n;
    }
}
