<?php

namespace Tests\Base\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tests\Base\Admin\FirewallSessionTrait;

/**
 * The back office's two colours as an administrator meets them: every page
 * carries the tokens derived from them (<style id="admin-theme">), the
 * settings page offers both fields with its live preview, and the preview
 * answers the tokens a pair would give - whole requests through the host
 * application's kernel (the omnibase harness), signed in by a session.
 */
class ThemeControllerTest extends KernelTestCase
{
    use FirewallSessionTrait;

    private EntityManagerInterface $em;
    private SessionInterface $session;
    private ?object $user = null;

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $user = new \App\Entity\User();
        $name = 'theme'.bin2hex(random_bytes(4));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($name);
        }
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword('test-'.$name);
        $user->setRoles(['ROLE_SUPERADMIN']);
        $this->em->persist($user);
        $this->em->flush();
        $this->user = $user;

        $this->session = static::getContainer()->get('session.factory')->createSession();
        self::signInto($this->session, $user, '/admin/settings');
    }

    protected function tearDown(): void
    {
        if (isset($this->em) && null !== $this->user) {
            $this->em->clear();
            if ($managed = $this->em->find($this->user::class, $this->user->getId())) {
                $this->em->remove($managed);
                $this->em->flush();
            }
        }
        parent::tearDown();
    }

    private function request(string $path): Response
    {
        $request = Request::create($path, 'GET');
        $request->cookies->set($this->session->getName(), $this->session->getId());

        return static::$kernel->handle($request);
    }

    public function testEveryPageCarriesTheTokensAndTheSettingsPageBothFields(): void
    {
        $html = (string) $this->request('/admin/settings')->getContent();

        $this->assertMatchesRegularExpression('/<style id="admin-theme">:root \{ --admin-bg: #[0-9a-f]{6};/', $html);
        $this->assertStringContainsString('admin-theme-primary', $html);
        $this->assertStringContainsString('admin-theme-secondary', $html);
        $this->assertStringContainsString('data-theme-preview', $html);
    }

    public function testThePreviewAnswersTheTokensOfAPairAndWhatItAdjusted(): void
    {
        $response = $this->request('/admin/settings/theme?primary=%23ffd600&secondary=%234e342e');
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame(['primary' => '#ffd600', 'secondary' => '#4e342e'], $data['colors']);
        $this->assertStringContainsString('--admin-accent:', $data['css']);
        $this->assertStringNotContainsString('--admin-accent: #ffd600;', explode('@media', $data['css'])[0], 'a yellow is darkened on white');
        $this->assertCount(1, $data['notes'], 'the accent, said in a sentence');
    }
}
