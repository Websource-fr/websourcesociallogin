<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Flow;

/**
 * Stockage serveur des états éphémères : autorisation en cours (state/nonce/PKCE), confirmation par mot de passe,
 * e-mail à fournir, e-mail à vérifier. Seul le HACHÉ du jeton est conservé ; chaque entrée expire et n'est consommable qu'une fois.
 */
interface FlowStoreInterface
{
    const AUTH = 'auth';
    const CONFIRM = 'confirm';
    const ASKMAIL = 'askmail';
    const VERIFY = 'verify';

    /** @param array<string,mixed> $payload */
    public function create(string $type, array $payload, int $ttl, string $bindHash, string $ipHash): string;

    /** @return array<string,mixed>|null */
    public function find(string $type, string $token, string $bindHash): ?array;

    /** @param array<string,mixed> $payload */
    public function update(string $type, string $token, array $payload): void;

    /** Lecture + suppression atomique (usage unique). @return array<string,mixed>|null */
    public function consume(string $type, string $token, string $bindHash): ?array;

    public function delete(string $type, string $token): void;

    /** nombre d'entrées créées par cette empreinte d'IP dans les $seconds dernières secondes */
    public function countRecent(string $ipHash, int $seconds): int;
}
