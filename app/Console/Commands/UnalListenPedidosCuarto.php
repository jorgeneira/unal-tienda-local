<?php

namespace App\Console\Commands;

use App\Repositories\ColaPedidos;
use Excel;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UnalListenPedidosCuarto extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'unal:listenPedidosCuarto';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Escucha la API remota y registra nuevos pedidos';

	/**
	 * @var Client
	 */
	protected $cliente;

	/**
	 * @var ColaPedidos
	 */
	protected $cola;

	/**
	 * @var Process
	 */
	protected $excelProcess;

	/**
	 * Create a new command instance.
	 *
	 */
	public function __construct() {

		parent::__construct();

		$this->cola = new ColaPedidos;

		$this->cliente = new Client;

	}

	/**
	 * Procesa nuevos pedidos en el servidor
	 *
	 */
	public function procesaNuevosPedidos() {


		$res = $this->cliente->get(config('roomsApi.pedidosURL'));

		$lista = json_decode($res->getBody()->getContents(), true);

		if (count($lista)) {

			$this->cola->ponerEnCola($lista);

			$this->marcarLeidos($lista);

			$this->info('Productos agregados a la cola');

			return true;
		}

		return false;
	}

	public function marcarLeidos($lista) {

		$idsPedidos = array_pluck($lista, 'id');

		$this->cliente->post(config('roomsApi.pedidosURL'), [
			'form_params' => [
				'pedidos' => $idsPedidos
			]
		]);

	}


	public function procesaCola() {


		if((!$this->excelProcess || !$this->excelProcess->isRunning()) && ($siguentePedido = $this->cola->getFirst())){


			try{

				$this->crearExcel($siguentePedido);

				$this->cola->limpiarUltimoPedido();

			}catch (\Exception $e){

				$this->info('|-- Archivo excel bloqueado. Esperando...');

				return false;

			}
			$this->excelProcess = new Process('excel "'.config("roomsApi.macroInicial").'"');

			$this->excelProcess->start();

			$this->info('|- abriendo excel');

			return true;

		}

		return false;

	}

	public function crearExcel($siguentePedido) {

		Excel::create('pedido', function ($excel) use ($siguentePedido) {

			$excel->sheet('pedidoInfo', function ($sheet) use ($siguentePedido) {


				/** @var \Maatwebsite\Excel\Classes\LaravelExcelWorksheet $sheet */
				$sheet->fromArray([$siguentePedido]);

			});

		})->store('xlsx', storage_path('pedidos'));

	}


	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {

		$this->info('Esperando pedidos...');
		
		while (1) {

			$this->procesaNuevosPedidos();

			$this->procesaCola();

			sleep(5);
		}
	}
}
