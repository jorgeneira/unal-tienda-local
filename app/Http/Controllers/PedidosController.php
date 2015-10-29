<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use App\Repositories\ColaPedidos;

class PedidosController extends Controller
{
	public function index() {

		$cola = (new ColaPedidos)->getCola();

		return view('colaIndex', compact('cola'));

	}
}
