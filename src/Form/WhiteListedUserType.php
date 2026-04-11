<?php

namespace App\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class WhiteListedUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email address is required.',
                    ]),
                    new Email([
                        'message' => 'Please enter a valid email address.',
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                ],
            ])
            ->add('active', ChoiceType::class, [
                'choices' => [
                    'Active' => true,
                    'Inactive' => false,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true
        ]);
    }
}
