<?php

declare(strict_types=1);

namespace App\Form;

use App\Session\SessionData;
use App\Session\TaskSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A network session: date, flight plan (code typed with suggestions, sheet on eplaneur.fr),
 * dedicated group, Condor Club circuit, stop join of the server, notes; once flown, the results file of the server.
 *
 * @extends AbstractType<SessionData>
 */
final class SessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, ['label' => 'Date', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure du briefing',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Heure de métropole. La Réunion : +2 h en été, +3 h en hiver.',
                // Unknown for the sessions brought from eplaneur.fr
                'required' => !$options['with_results'],
                'constraints' => $options['with_results'] ? [] : [new Assert\NotNull(message: 'Indiquez l’heure du briefing.')],
            ])
            ->add('flightPlan', TextType::class, [
                'label' => 'Plan de vol',
                'help' => 'Tapez le code ou le parcours : les plans de vol déjà enregistrés s’affichent. Un nouveau code est enregistré avec sa fiche pour les prochaines fois.',
                'attr' => ['placeholder' => 'C3-c046a', 'autocomplete' => 'off', 'list' => 'flight-plan-suggestions', 'data-flight-plan-field-target' => 'name', 'data-action' => 'flight-plan-field#search change->flight-plan-field#pick'],
            ])
            ->add('sheetUrl', UrlType::class, [
                'label' => 'Fiche du plan de vol sur eplaneur.fr',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Remplie pour un plan de vol déjà enregistré ; pour un nouveau, l’adresse de sa page « Afficher FPL ».',
                'attr' => ['data-flight-plan-field-target' => 'url'],
            ])
            ->add('dedicatedTo', TextType::class, [
                'label' => 'Session dédiée',
                'required' => false,
                'help' => 'Laissez vide pour une session ouverte à tous ; sinon, le groupe à qui elle est réservée (Epitech, Compétition…).',
            ])
            ->add('condorClubUrl', UrlType::class, [
                'label' => 'Circuit Condor Club',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Le lien du circuit (condor.club/task/…) : où les pilotes enregistrent leur performance. Ses réglages (heure, départ, pénalités, météo) sont repris de Condor Club.',
            ])
            ->add('flightPlanFile', FileType::class, [
                'label' => 'Plan de vol Condor (.fpl)',
                'required' => false,
                'help' => 'Si le circuit n’est pas sur Condor Club : le fichier .fpl de la session. Ses réglages sont repris de la même façon (ils remplacent ceux de Condor Club).',
                'attr' => ['accept' => '.fpl'],
            ])
            ->add('joinTime', IntegerType::class, [
                'label' => 'Stop join (minutes)',
                'required' => false,
                'help' => \sprintf('Le temps que le serveur laisse pour rejoindre après l’heure du plan de vol ; le délai avant le départ court ensuite. Vide : %d min, le réglage du serveur du club. Repris tout seul de l’IGC exporté par Condor d’une régate.', TaskSettings::JOIN_TIME),
                'attr' => ['min' => 0, 'max' => 120, 'placeholder' => (string) TaskSettings::JOIN_TIME],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Informations pratiques',
                'required' => false,
                'help' => 'Serveur, type de départ, salon Discord…',
                'attr' => ['rows' => 4, 'maxlength' => 3000],
            ]);
        if ($options['with_results']) {
            $builder->add('results', FileType::class, [
                'label' => 'Résultats du serveur',
                'required' => false,
                'help' => 'Le fichier « DSRace-…-on-….csv » écrit par le serveur dédié à la fin de la course. Les traces des pilotes de cette course s’y rattachent.',
                'attr' => ['accept' => '.csv,text/csv'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionData::class, 'with_results' => false]);
        $resolver->setAllowedTypes('with_results', 'bool');
    }
}
