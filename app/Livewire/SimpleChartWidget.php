<?php

namespace App\Livewire;

use Livewire\Component;

class SimpleChartWidget extends Component
{
    public $labels = ['Jan', 'Feb', 'Mar', 'Apr'];
    public $data = [10, 5, 15, 7];

    public function render()
    {
        return view('livewire.simple-chart-widget');
    }
}
