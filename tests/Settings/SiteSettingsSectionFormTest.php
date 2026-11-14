<?php

namespace Tests\Base\Admin\Settings;

use Base\Admin\Settings\SiteSettingsSection;
use Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType;
use Omnibridge\Symfony\Omnibase\Field\Type\ImageType;
use Omnibridge\Symfony\Omnibase\Form\Type\LayoutSettingListType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;

/**
 * The settings page's logo and launch date (SiteSettingsSection) as LayoutSettingListType builds
 * them: an image upload with its options (a size limit, the stored file kept by a plain Save) and a
 * date converted from the string the bag stores. The section names the two form types by their new
 * names, which the bridge's LayoutSettingListType reads as their own from omnibridge/symfony 1.1.2.
 */
class SiteSettingsSectionFormTest extends KernelTestCase
{
    private const LOGO = 'base.settings.logo.admin';
    private const LAUNCH = 'base.settings.launchdate';
    private const STORED_DATE = '2026-11-14 10:30:00';

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('Base\\Service\\SettingBag')) {
            self::markTestSkipped('Requires omnisense/omnibase and a host application (the omnibase harness).');
        }

        self::bootKernel();
    }

    public function testTheSectionNamesTheFormTypesByTheirNewNames(): void
    {
        $fields = $this->fields();

        $this->assertSame(ImageType::class, $fields[self::LOGO]['form_type']);
        $this->assertSame(DateTimePickerType::class, $fields[self::LAUNCH]['form_type']);
    }

    public function testTheLogoIsAnUploadWithItsOptions(): void
    {
        $value = $this->value($this->form(), self::LOGO);
        $options = $value->getConfig()->getOptions();

        $this->assertSame(ImageType::class, \get_class($value->getConfig()->getType()->getInnerType()));
        $this->assertNotNull($options['max_size'], 'an upload size limit');
        $this->assertSame('logo.png', $options['empty_data'], 'the stored file, what a plain Save binds');
    }

    public function testTheLaunchDateIsADate(): void
    {
        $date = $this->value($this->form(), self::LAUNCH)->getData();

        $this->assertInstanceOf(\DateTimeInterface::class, $date);
        $this->assertSame(self::STORED_DATE, $date->format('Y-m-d H:i:s'));
    }

    /** @return array<string, array<string, mixed>> */
    private function fields(): array
    {
        return (new SiteSettingsSection(static::getContainer()->get('translator')))->getFields();
    }

    /** The settings form with the section's logo and launch date, both stored beforehand. */
    private function form(): FormInterface
    {
        $bag = static::getContainer()->get('Base\\Service\\SettingBag');
        $bag->set(self::LOGO, 'logo.png');
        $bag->set(self::LAUNCH, self::STORED_DATE);
        $fields = array_intersect_key($this->fields(), [self::LOGO => true, self::LAUNCH => true]);

        return static::getContainer()->get('form.factory')->create(LayoutSettingListType::class, null, [
            'csrf_protection' => false,
            'fields' => $fields,
        ]);
    }

    /** The field holding a setting's value (unv > locale > _collection > setting > value). */
    private function value(FormInterface $form, string $path): FormInterface
    {
        $locale = static::getContainer()->get('Base\\Service\\Localizer')->getDefaultLocale();

        return $form->get('unv')->get($locale)->get('_collection')->get(str_replace('.', '-', $path))->get('value');
    }
}
