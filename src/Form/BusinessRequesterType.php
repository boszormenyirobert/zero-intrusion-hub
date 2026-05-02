<?php

namespace App\Form;

use App\DTO\BusinessRequestDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints\Choice;

class BusinessRequesterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('businessModel', HiddenType::class, [
            'constraints' => [
                new Choice(
                    choices: BusinessRequestDTO::allowedBusinessModels(),
                    message: 'Invalid business model selected.',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BusinessRequestDTO::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'identity_requester',
        ]);
    }
}
