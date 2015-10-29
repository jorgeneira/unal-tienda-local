<?php
/**
 * Created by PhpStorm.
 * User: Joren
 * Date: 27/10/2015
 * Time: 9:02
 */

namespace App\Repositories;


class ColaPedidos {

	/** @var \Illuminate\Support\Collection $lista */
	protected $lista;

	private $keyCola = "colaPedidos";

	public function __construct() {

		$this->lista = \Cache::get($this->keyCola, collect([]));

	}

	public function getFirst(){

		return $this->lista->first();

	}
	
	public function ponerEnCola($pedidos) {

		$this->lista = $this->lista->merge($pedidos);

		\Cache::put($this->keyCola, $this->lista, 1440);

	}

	public function getCola() {
		return $this->lista;
	}

	public function limpiarUltimoPedido() {

		$this->lista->splice(0,1);

		\Cache::put($this->keyCola, $this->lista, 1440);

	}


}