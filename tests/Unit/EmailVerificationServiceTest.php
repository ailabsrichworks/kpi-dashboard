<?php

namespace Tests\Unit;

use App\Services\EmailVerificationService;
use PHPUnit\Framework\TestCase;

/**
 * The real constructor does a live DNS lookup (checkdnsrr) -- these tests
 * inject a fake resolver instead so they never touch the network, then
 * separately confirm the domain-extraction logic itself (malformed
 * addresses, no "@", etc.) never even reaches the resolver.
 */
class EmailVerificationServiceTest extends TestCase
{
    public function test_domain_can_receive_mail_defers_to_the_injected_resolver(): void
    {
        $service = new EmailVerificationService(fn (string $domain) => $domain === 'richworks.com');

        $this->assertTrue($service->domainCanReceiveMail('anyone@richworks.com'));
        $this->assertFalse($service->domainCanReceiveMail('anyone@thisdomaindoesnotexist.invalid'));
    }

    public function test_domain_can_receive_mail_is_false_for_an_address_with_no_domain(): void
    {
        $resolverCalled = false;
        $service = new EmailVerificationService(function () use (&$resolverCalled) {
            $resolverCalled = true;
            return true;
        });

        $this->assertFalse($service->domainCanReceiveMail('not-an-email'));
        $this->assertFalse($resolverCalled);
    }

    public function test_domain_can_receive_mail_is_false_when_the_resolver_throws(): void
    {
        $service = new EmailVerificationService(function () {
            throw new \RuntimeException('DNS lookup failed');
        });

        $this->assertFalse($service->domainCanReceiveMail('anyone@richworks.com'));
    }
}
