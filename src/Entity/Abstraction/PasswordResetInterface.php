<?php
/**
 * This is automatically generated file using the Codific Prototizer.
 *
 * PHP version 8
 *
 * @category PHP
 *
 * @author   CODIFIC <info@codific.com>
 *
 * @see     http://codific.com
 */

declare(strict_types=1);

namespace App\Entity\Abstraction;

interface PasswordResetInterface
{
    /**
     * @return mixed
     */
    public function setPasswordResetHash(?string $passwordResetHash);

    public function getPasswordResetHash(): ?string;

    /**
     * @return mixed
     */
    public function setPasswordResetHashExpiration(?\DateTime $passwordResetHashExpiration);

    public function getPasswordResetHashExpiration(): ?\DateTime;

    /**
     * Transient, in-memory only. Holds the plaintext token during the request
     * in which the reset was generated so the mailer can embed it in the link.
     * The persisted column stores a SHA-256 hash of this value.
     *
     * @return mixed
     */
    public function setPlaintextPasswordResetHash(?string $plaintext);

    public function getPlaintextPasswordResetHash(): ?string;
}
