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
use Spatie\LivewireWizard\Enums\StepStatus;
use Spatie\LivewireWizard\Support\Step;

abstract class WizardComponent extends Component
{
    use MountsWizard;

    public array $activities = [];
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
            ->each(function (array $step) {
                if (! is_a($step['class'], StepComponent::class, true)) {
                    throw InvalidStepComponent::doesNotExtendStepComponent(static::class, $step['class']);
                }
            })
            ->map(function (array $step) use(&$index) {
                $alias = Livewire::getAlias($step['class']);

                if (is_null($alias)) {
                    throw InvalidStepComponent::notRegisteredWithLivewire(static::class, $step['class']);
                }

                return $step['name'];
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
                'activities' => $this->activities,
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

    public function getSteps(){
        $currentStepName = $this->currentStepName;
        return collect($this->stepNames())
            ->map(function (string $stepName) use (&$currentFound, $currentStepName) {

                $componentName = substr($stepName, strpos($stepName, '-') + 2);
                $className = Livewire::getClass($componentName);
                $info = (new $className())->stepInfo();

                $info['step_number'] = intval(trim(substr($stepName, 0, strpos($stepName, '-'))));

                $status = $currentFound ? StepStatus::Next : StepStatus::Previous;

                if ($stepName == $currentStepName) {
                    $currentFound = true;
                    $status = StepStatus::Current;
                }
                $info['status'] = $status;

                return new Step($stepName, $info, $status);
            })
            ->toArray();
    }

    /** @return class-string<State> */
    public function stateClass(): string
    {
        return State::class;
    }
}
