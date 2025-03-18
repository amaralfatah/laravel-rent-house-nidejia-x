<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Number;

class StatsOverview extends BaseWidget
{
    private function getPercentage($from, $to)
    {
        return ($to - $from) / ($to + $from / 2) * 100;
    }
    protected function getStats(): array
    {
        $newListing = Listing::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)->count();

        $transaction = Transaction::whereStatus('approved')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year);
        $prevTransaction = Transaction::whereStatus('approved')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year);
        $transactionPercentage = $this->getPercentage($prevTransaction->count(), $transaction->count());
        $revenuePercentage = $this->getPercentage($prevTransaction->sum('total_price'), $transaction->sum('total_price'));

        return [
            Stat::make('New listings of the month', $newListing),
            Stat::make('New transactions of the month', $transaction->count())
                ->description($transactionPercentage > 0 ? "{$transactionPercentage}% increase" : "{$transactionPercentage}% decrease")
                ->descriptionIcon($transactionPercentage > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($transactionPercentage > 0 ? 'success' : 'danger'),
            Stat::make('Revenue of the month', Number::currency($transaction->sum(column: 'total_price'), 'USD'))
                ->description($revenuePercentage > 0 ? "{$revenuePercentage}% increase" : "{$revenuePercentage}% decrease")
                ->descriptionIcon($revenuePercentage > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($revenuePercentage > 0 ? 'success' : 'danger'),
        ];
    }
}
