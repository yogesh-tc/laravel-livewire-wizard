<?php

namespace Spatie\LivewireWizard\Components;

use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\LivewireWizard\Components\Concerns\MountsWizard;
use Spatie\LivewireWizard\Exceptions\InvalidStepComponent;
use Spatie\LivewireWizard\Exceptions\NoNextStep;
use Spatie\LivewireWizard\Exceptions\NoPreviousStep;
use Spatie\LivewireWizard\Exceptions\NoStepsReturned;
use Spatie\LivewireWizard\Exceptions\StepDoesNotExist;
use Spatie\LivewireWizard\Support\State;

abstract class WizardComponent extends Component
{
    use MountsWizard;

    public array $allStepState = [];
    public ?string $currentStepName = null;

    public int $currentStepNumber = 1;

    protected $listeners = [
        'previousStep',
        'nextStep',
        'showStep',
    ];

    /** @return <int, class-string<StepComponent> */
    abstract public function steps(): array;

    public function initialState(): ?array
    {
        return null;
    }

    public function stepNames(): Collection
    {
        $index = 1;
        $steps = collect($this->steps())
            ->each(function (string $stepClassName) {
                if (! is_a($stepClassName, StepComponent::class, true)) {
                    throw InvalidStepComponent::doesNotExtendStepComponent(static::class, $stepClassName);
                }
            })
            ->map(function (string $stepClassName) use(&$index) {
                $alias = Livewire::getAlias($stepClassName);

                if (is_null($alias)) {
                    throw InvalidStepComponent::notRegisteredWithLivewire(static::class, $stepClassName);
                }

                return $index++ . ' - '. $alias;
            });

        if ($steps->isEmpty()) {
            throw NoStepsReturned::make(static::class);
        }

        return $steps;
    }

    public function previousStep(array $currentStepState)
    {
        $previousStep = collect($this->stepNames())
            ->before(fn (string $step) => $step === $this->currentStepName);

        if (! $previousStep) {
            throw NoPreviousStep::make(self::class, $this->currentStepName);
        }

        $this->showStep($previousStep, $currentStepState);
    }

    public function nextStep(array $currentStepState)
    {
        $nextStep = collect($this->stepNames())
            ->after(fn (string $step) => $step === $this->currentStepName);

        if (! $nextStep) {
            throw NoNextStep::make(self::class, $this->currentStepName);
        }

        $this->showStep($nextStep, $currentStepState);
    }

    public function showStep($toStepName, array $currentStepState = [])
    {
        if ($this->currentStepName) {
            $this->setStepState($this->currentStepName, $currentStepState);
        }
        $this->currentStepNumber = intval(trim(substr($toStepName, 0, strpos($toStepName, '-'))));
        $this->currentStepName = $toStepName;
    }

    public function setStepState(string $step, array $state = []): void
    {
        if (! $this->stepNames()->contains($step)) {
            throw StepDoesNotExist::doesNotHaveState($step);
        }

        $this->allStepState[$step] = $state;
    }

    public function getCurrentStepState(?string $step = null): array
    {
        $stepName = $step ?? $this->currentStepName;

        $stepName = class_exists($stepName)
            ? Livewire::getAlias($stepName)
            : $stepName;

        throw_if(
            ! $this->stepNames()->contains($stepName),
            StepDoesNotExist::stepNotFound($stepName)
        );

        return array_merge(
            $this->allStepState[$stepName] ?? [],
            [
                'allStepNames' => $this->stepNames()->toArray(),
                'allStepsState' => $this->allStepState,
                'stateClassName' => $this->stateClass(),
                'currentStepNumber' => $this->currentStepNumber
            ],
        );
    }

    public function render()
    {
        $currentStepState = $this->getCurrentStepState();
        $stepName = substr($this->currentStepName, strpos($this->currentStepName, '-') + 2);
        $steps = $this->stepNames();
        return view('livewire-wizard::wizard', compact('currentStepState', 'stepName', 'steps'));
    }

    /** @return class-string<State> */
    public function stateClass(): string
    {
        return State::class;
    }
}
