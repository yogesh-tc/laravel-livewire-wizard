<?php

namespace Spatie\LivewireWizard\Components\Concerns;

use Livewire\Livewire;
use Spatie\LivewireWizard\Enums\StepStatus;
use Spatie\LivewireWizard\Support\Step;

trait StepAware
{
    public array $steps = [];

    public function bootedStepAware()
    {
        $currentFound = false;

        $currentStepName = Livewire::getAlias(static::class);

        $this->steps = collect($this->allStepNames)
            ->map(function (string $stepName) use (&$currentFound, $currentStepName) {
                
		$componentName = substr($stepName, strpos($stepName, '-') + 2);
                $className = Livewire::getClass($componentName);
                $info = (new $className())->stepInfo();

                $info['step_number'] = intval(trim(substr($stepName, 0, strpos($stepName, '-'))));

                $status = $currentFound ? StepStatus::Next : StepStatus::Previous;

                /*if ($stepNumber++ == $currentNumber) {
                    $currentFound = true;
                    $status = StepStatus::Current;
                }*/

                return new Step($stepName, $info, $status);
            })
            ->toArray();
    }
}
