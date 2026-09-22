<?php

namespace App\Services;

/**
 * The only two things about an email address this app can honestly claim to
 * check without actually delivering a message to it (most providers,
 * Gmail included, treat mailbox-probing as abuse and won't confirm one way
 * or the other): whether it belongs to an employee already on file, or --
 * if not -- whether its domain is even capable of receiving mail at all (a
 * real MX/A record). Neither proves the specific mailbox exists; both
 * catch the overwhelmingly common failure mode of a typo'd address.
 */
class EmailVerificationService
{
    /** @var callable(string $domain): bool */
    private $domainAcceptsMail;

    public function __construct(?callable $domainAcceptsMail = null)
    {
        $this->domainAcceptsMail = $domainAcceptsMail
            ?? fn (string $domain) => checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }

    public function domainCanReceiveMail(string $email): bool
    {
        $domain = substr((string) strrchr($email, '@'), 1);

        if ($domain === '') {
            return false;
        }

        try {
            return (bool) ($this->domainAcceptsMail)($domain);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
