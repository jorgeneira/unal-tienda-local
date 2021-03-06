<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
	/**
	 * The Artisan commands provided by your application.
	 *
	 * @var array
	 */
	protected $commands = [
		\App\Console\Commands\UnalListenPedidosCuarto::class,
		\App\Console\Commands\UnalListenPedidosCocina::class,
		\App\Console\Commands\UnalProcessOutputCuarto::class,
		\App\Console\Commands\UnalProcessOutputCocina::class,
	];

	/**
	 * Define the application's command schedule.
	 *
	 * @param  \Illuminate\Console\Scheduling\Schedule $schedule
	 * @return void
	 */
	protected function schedule(Schedule $schedule) {

	}
}
