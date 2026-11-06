<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        App::setLocale('es');
        Carbon::setLocale('es');

        Action::configureUsing(function (Action $action): void {
            $action->slideOver();
        });

        Section::configureUsing(function (Section $section): void {
            $section->columnSpanFull();
        });

        Grid::configureUsing(function (Grid $grid): void {
            $grid->columnSpanFull();
        });
    }
}
