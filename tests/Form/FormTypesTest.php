<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\DTO\BusinessRequestDTO;
use App\DTO\CorporateDataDTO;
use App\DTO\InstanceSettingsInputDTO;
use App\DTO\ReplaceDeviceDTO;
use App\DTO\ReplaceDevicePinDTO;
use App\DTO\WhitelistedUserInputDTO;
use App\Form\BusinessRequesterType;
use App\Form\CorporateType;
use App\Form\IdentityRequesterType;
use App\Form\InstanceSettingsType;
use App\Form\ReplaceDevicePinType;
use App\Form\ReplaceDeviceType;
use App\Form\SystemRegistrationRequesterType;
use App\Form\WhiteListedUserType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use PHPUnit\Framework\TestCase;

final class FormTypesTest extends TestCase
{
    public function testBusinessRequesterTypeBuildsExpectedForm(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::once())
            ->method('add')
            ->with('businessModel', self::anything(), self::callback(static function (array $options): bool {
                return isset($options['constraints'][0])
                    && $options['constraints'][0] instanceof Choice;
            }))
            ->willReturnSelf();

        (new BusinessRequesterType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new BusinessRequesterType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(BusinessRequestDTO::class, $options['data_class']);
    }

    public function testCorporateTypeBuildsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::exactly(4))->method('add')->willReturnSelf();

        (new CorporateType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new CorporateType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(CorporateDataDTO::class, $options['data_class']);
    }

    public function testIdentityRequesterTypeConfiguresCsrfProtection(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');

        (new IdentityRequesterType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new IdentityRequesterType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertTrue($options['csrf_protection']);
        self::assertSame('identity_requester', $options['csrf_token_id']);
    }

    public function testInstanceSettingsTypeBuildsCheckboxField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::once())->method('add')->with('initialization', self::anything())->willReturnSelf();

        (new InstanceSettingsType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new InstanceSettingsType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(InstanceSettingsInputDTO::class, $options['data_class']);
    }

    public function testReplaceDevicePinTypeBuildsPinField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::once())->method('add')->with('pin', self::anything())->willReturnSelf();

        (new ReplaceDevicePinType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new ReplaceDevicePinType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(ReplaceDevicePinDTO::class, $options['data_class']);
    }

    public function testReplaceDeviceTypeBuildsEmailAndPhoneFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::exactly(2))->method('add')->willReturnSelf();

        (new ReplaceDeviceType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new ReplaceDeviceType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(ReplaceDeviceDTO::class, $options['data_class']);
    }

    public function testSystemRegistrationRequesterTypeConfiguresExpectedCsrfToken(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');

        (new SystemRegistrationRequesterType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new SystemRegistrationRequesterType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertTrue($options['csrf_protection']);
        self::assertSame('authentication_qr_identity', $options['csrf_token_id']);
    }

    public function testWhitelistedUserTypeBuildsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::exactly(2))
            ->method('add')
            ->withConsecutive(
                ['email', self::anything(), self::callback(static function (array $options): bool {
                    return ($options['constraints'][0] ?? null) instanceof NotBlank
                        && ($options['constraints'][1] ?? null) instanceof Email;
                })],
                ['active', self::anything(), self::callback(static fn (array $options): bool => isset($options['choices']['Active'], $options['choices']['Inactive']))]
            )
            ->willReturnSelf();

        (new WhiteListedUserType())->buildForm($builder, []);

        $resolver = new OptionsResolver();
        (new WhiteListedUserType())->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertSame(WhitelistedUserInputDTO::class, $options['data_class']);
    }
}
