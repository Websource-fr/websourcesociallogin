<?php

declare(strict_types=1);

/**
 * @author    Websource
 * @copyright Websource
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Websource\SocialLogin\Provider;

/** Identité normalisée renvoyée par un réseau. */
final class Profile
{
    /** @var string */
    public $id;
    /** @var string */
    public $email;
    /** @var bool */
    public $emailVerified;
    /** @var string */
    public $firstname;
    /** @var string */
    public $lastname;

    public function __construct(string $id, string $email = '', bool $emailVerified = false, string $firstname = '', string $lastname = '')
    {
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->emailVerified = $emailVerified && $this->email !== '';
        $this->firstname = $firstname;
        $this->lastname = $lastname;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'ev' => $this->emailVerified, 'fn' => $this->firstname, 'ln' => $this->lastname];
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self((string) ($a['id'] ?? ''), (string) ($a['email'] ?? ''), !empty($a['ev']), (string) ($a['fn'] ?? ''), (string) ($a['ln'] ?? ''));
    }
}
