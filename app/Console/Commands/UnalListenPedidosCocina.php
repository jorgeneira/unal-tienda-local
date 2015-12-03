<?php

namespace App\Console\Commands;

use App\Repositories\ColaPedidos;
use Excel;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;


class UnalListenPedidosCocina extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */

	protected $signature = 'unal:listenPedidosCocina';

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
	protected $inventorProcess;

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


		$res = $this->cliente->get(config('kitchensApi.pedidosURL'));

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


		$this->cliente->post(config('kitchensApi.pedidosURL'), [
			'form_params' => [
				'pedidos' => $idsPedidos
			]
		]);

	}


	public function procesaCola() {


		if((!$this->inventorProcess || !$this->inventorProcess->isRunning()) && ($siguentePedido = $this->cola->getFirst())){


			try{

				$this->crearExcel($siguentePedido);

				$this->cola->limpiarUltimoPedido();

			}catch (\Exception $e){


				$this->info($e->getMessage());


				$this->info('|-- Archivo inventor bloqueado. Esperando...');

				return false;

			}

			$this->inventorProcess = new Process('inventor "'.config("kitchensApi.macroInicial").'"');

			$this->inventorProcess->start();

			$this->info('|- abriendo inventor');

			return true;

		}

		return false;

	}


	public function buildExcelSheets($siguientePedido){

		$hojas = [];

		$datosHoja1 = [
			'ancho',
			'largo',
			'alto',
			'tipo_cocina',
			'tipo_estufa',
			'tipo_lavaplatos',
			'extractor',
			'seccion_estufa',
			'seccion_lavaplatos',
			'modulo_estufa',
			'modulo_lavaplatos',
			'modulo_lavaplatos',
			'modulos_seccion_1',
			'modulos_seccion_2',
			'modulos_seccion_3',
			'color',
			'material',
			'meson',
			'manija',
		];

		$hojas['Hoja1'] = [];


		foreach($datosHoja1 as $key){
			$hojas['Hoja1'][$key] = $siguientePedido[$key];
		}

		for($i = 1; $i <= 3 ; $i++){

			$hojas["Seccion {$i}"] = array_key_exists($i, $siguientePedido['secciones']) ? $siguientePedido['secciones'][$i] : [];

		}

		$hojas["Pedido"] = [[
			'pedidoID' => $siguientePedido['id']
		]];

		$hojas["Seccion {$siguientePedido['seccion_estufa']}"]["modulo_{$siguientePedido['modulo_estufa']}_sup"] = "-";
		$hojas["Seccion {$siguientePedido['seccion_estufa']}"]["modulo_{$siguientePedido['modulo_estufa']}_inf"] = "-";

		$hojas["Seccion {$siguientePedido['seccion_lavaplatos']}"]["modulo_{$siguientePedido['modulo_lavaplatos']}_sup"] = "-";
		$hojas["Seccion {$siguientePedido['seccion_lavaplatos']}"]["modulo_{$siguientePedido['modulo_lavaplatos']}_inf"] = "-";



		return $hojas;

	}

	public function crearExcel($siguentePedido) {

		$listaHojas = $this->buildExcelSheets($siguentePedido);

		Excel::create('Ejemplo', function ($excel) use ($listaHojas) {

			foreach($listaHojas as $hojaName => $hojaData){

				$excel->sheet($hojaName, function ($sheet) use ($hojaData) {


					/** @var \Maatwebsite\Excel\Classes\LaravelExcelWorksheet $sheet */
					$sheet->fromArray($hojaData,null,'A1',true);

				});

			}

		})->store('xlsx', storage_path('macros/cocina/'));

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
