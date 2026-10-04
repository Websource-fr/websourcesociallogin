<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Flow;

use Websource\SocialLogin\Security\Token;

/** Table websourcesociallogin_flow. */
final class DbFlowStore implements FlowStoreInterface
{
    const TABLE = 'websourcesociallogin_flow';

    /** @var int */
    private $shop;

    public function __construct(int $idShop)
    {
        $this->shop = $idShop;
    }

    private function t(): string
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    public function create(string $type, array $payload, int $ttl, string $bindHash, string $ipHash): string
    {
        $db = \Db::getInstance();
        // ménage opportuniste des entrées expirées
        $db->execute('DELETE FROM `' . $this->t() . '` WHERE expires < ' . (time() - 60));
        $token = Token::random(32);
        $db->insert(self::TABLE, [
            'token_hash' => pSQL(Token::hash($token)),
            'type' => pSQL($type),
            'payload' => pSQL((string) json_encode($payload), false),
            'bind_hash' => pSQL($bindHash),
            'ip_hash' => pSQL($ipHash),
            'id_shop' => $this->shop,
            'expires' => time() + $ttl,
            'created' => time(),
        ]);

        return $token;
    }

    /** @return array<string,mixed>|null */
    private function load(string $type, string $token, string $bindHash): ?array
    {
        if (!Token::looksValid($token)) {
            return null;
        }
        $row = \Db::getInstance()->getRow('SELECT payload, bind_hash FROM `' . $this->t() . '` WHERE token_hash = \'' . pSQL(Token::hash($token)) . '\' AND type = \'' . pSQL($type) . '\' AND expires >= ' . time());
        if (!$row || !hash_equals((string) $row['bind_hash'], $bindHash)) {
            return null;
        }
        $payload = json_decode((string) $row['payload'], true);

        return is_array($payload) ? $payload : null;
    }

    public function find(string $type, string $token, string $bindHash): ?array
    {
        return $this->load($type, $token, $bindHash);
    }

    public function update(string $type, string $token, array $payload): void
    {
        \Db::getInstance()->update(self::TABLE, ['payload' => pSQL((string) json_encode($payload), false)], 'token_hash = \'' . pSQL(Token::hash($token)) . '\' AND type = \'' . pSQL($type) . '\'');
    }

    public function consume(string $type, string $token, string $bindHash): ?array
    {
        $payload = $this->load($type, $token, $bindHash);
        if ($payload === null) {
            return null;
        }
        $db = \Db::getInstance();
        $db->execute('DELETE FROM `' . $this->t() . '` WHERE token_hash = \'' . pSQL(Token::hash($token)) . '\'');

        // usage unique : si une requête concurrente l'a déjà consommé, aucune ligne n'a été supprimée
        return $db->Affected_Rows() > 0 ? $payload : null;
    }

    public function delete(string $type, string $token): void
    {
        \Db::getInstance()->execute('DELETE FROM `' . $this->t() . '` WHERE token_hash = \'' . pSQL(Token::hash($token)) . '\' AND type = \'' . pSQL($type) . '\'');
    }

    public function countRecent(string $ipHash, int $seconds): int
    {
        return (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . $this->t() . '` WHERE ip_hash = \'' . pSQL($ipHash) . '\' AND created >= ' . (time() - $seconds));
    }
}
