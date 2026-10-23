<?php

namespace Tests\Base\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * A user signed in as after a real sign-in: their token in the session, under
 * the key of the firewall that guards the address asked - "_security_" and
 * the firewall's context, its name when it has none. The harness's firewall
 * is "main"; an application's may be another, or share a context
 * (lesringostars: "rescue" for /admin, "main" elsewhere, both "common").
 */
trait FirewallSessionTrait
{
    protected static function signInto(SessionInterface $session, object $user, string $path): void
    {
        $firewall = static::getContainer()->get('security.firewall.map')->getFirewallConfig(Request::create($path));
        $name = $firewall?->getName() ?? 'main';

        $session->set('_security_'.($firewall?->getContext() ?? $name), serialize(new UsernamePasswordToken($user, $name, $user->getRoles())));
        $session->save();
    }
}
