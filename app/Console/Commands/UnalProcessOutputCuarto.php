<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Excel;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UnalProcessOutputCuarto extends Command {

    private $emailClientData = [];
    private $emailProvidersData = [];
    private $outputData = [];
    private $remoteDataProviders = [];
    private $remoteData = [];
    private $wordProcess;

    /**
     * @var Client
     */
    protected $cliente;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unal:processOutputCuarto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process output data from the VBA macro.';


    public function __construct() {
        parent::__construct();

        $this->cliente = new Client();
    }

    /**
     * @return array
     */
    private function readOutput() {

        /** @var \Maatwebsite\Excel\Readers\LaravelExcelReader $data */
        $data = Excel::load(config('roomsApi.datosSalidaPath'));

        return $data->toArray();

    }

    private function crearExcel() {

        $this->closeExcelIfOpen();

        Excel::create('datosCotizacion', function ($excel) {

            /** @var \Maatwebsite\Excel\Readers\LaravelExcelReader $excel */
            $excel->sheet('pedidoInfo', function ($sheet) {


                /** @var \Maatwebsite\Excel\Classes\LaravelExcelWorksheet $sheet */
                $sheet->fromArray([$this->emailClientData]);

            });

        })->store('xlsx', storage_path('macros/emails/excel'));

    }


    private function parseName($name) {
        $names = [];
        $name = explode(' ', $name);
        $length = count($name);

        if ($length >= 3) {
            $names[0] = implode(' ', array_slice($name, 0, $length - 2));
            $names[1] = implode(' ', array_slice($name, -2, 2));
        }

        if ($length == 2) {
            $names = $name;
        }

        if ($length === 1) {
            $names[0] = $name[0];
            $names[1] = $name[0];
        }

        return $names;

    }

    private function getTotalCost() {

        $cost = 0;

        foreach ($this->outputData[0] as $producto) {
            $cost += $producto['cop'] * $producto['cantidad'];
        }

        return "$ " . number_format($cost, 2);

    }

    private function processExists($string) {

        $pNameUpper = strtoupper($string);
        $pNameLower = strtolower($string);

        $out = [];
        exec('tasklist | grep "' . $pNameUpper . '\|' . $pNameLower . '"', $out);

        return !!count($out);

    }

    private function closeExcelIfOpen() {

        if ($this->processExists('EXCEL')) {

            $this->info('Excel esta abierto. Intentando Cerrar...');

            exec('TASKKILL /f /im excel.exe');

            $this->info('Excel cerrado!');

        }

    }

    private function createProvidersExcel(){

        $this->closeExcelIfOpen();

        Excel::create('datosProviders', function ($excel) {

            foreach($this->emailProvidersData as $provider => $productList){

                /** @var \Maatwebsite\Excel\Readers\LaravelExcelReader $excel */
                $excel->sheet($productList[0]['nombre'], function ($sheet) use($productList) {


                    /** @var \Maatwebsite\Excel\Classes\LaravelExcelWorksheet $sheet */
                    $sheet->fromArray($productList);

                });

            }

        })->store('xlsx', storage_path('macros/emails/excel'));

    }

    /**
     * Build the final providers data to be storen in excel
     *
     * @return array
     */
    private function buildProvidersData(){

        $providerData = [];

        $products = collect($this->outputData[0])->map(function($item, $key){

            $item['provider'] = (int)$item['provider'];
            return $item;

        })->groupBy('provider')->toArray();

        foreach($products as $provider => $productList){

            $providerData[$provider] = [];

            foreach($productList as $pIndex => $pData){

                $providerData[$provider][] = [

                    'nombre' => $this->remoteDataProviders[$provider]['nombre'],
                    'email' => $this->remoteDataProviders[$provider]['email'],
                    'producto' => $pData['producto'].' - '.$pData['modelo'],
                    'cantidad' => $pData['cantidad']

                ];

            }


        }

        return $providerData;

    }

    /**
     * Build the client data array to be stored in excel format
     *
     */
    private function buildClientMailData() {

        $nombres = $this->parseName($this->remoteData['client']['nombre']);

        $this->emailClientData['idCotizacion'] = $this->remoteData['id'];
        $this->emailClientData['nombre'] = $nombres[0];
        $this->emailClientData['apellido'] = $nombres[1];
        $this->emailClientData['tipo'] = "Cuarto";
        $this->emailClientData['correo'] = $this->remoteData['client']['email'];
        $this->emailClientData['fecha'] = Carbon::parse($this->remoteData['created_at'])->toFormattedDateString();
        $this->emailClientData['total'] = $this->getTotalCost();
        $this->emailClientData['ancho'] = $this->remoteData['ancho'];
        $this->emailClientData['alto'] = $this->remoteData['alto'];
        $this->emailClientData['largo'] = $this->remoteData['largo'];
        $this->emailClientData['producto'] = $this->remoteData['producto'];
        $this->emailClientData['temperatura'] = $this->remoteData['temperatura'];

    }

    /**
     * @return $this
     */
    private function triggerProvidersMail() {


        $this->emailProvidersData = $this->buildProvidersData();

        $this->createProvidersExcel();

        pclose(popen('start /B ' . 'WINWORD "'.config('roomsApi.macroEmailProviders').'"', "r"));

        return $this;
    }

    /**
     * @return $this
     */
    private function triggerClientMail() {

        $this->buildClientMailData();

        $this->crearExcel();

        pclose(popen('start /B ' . 'WINWORD "'.config('roomsApi.macroEmailCliente').'"', "r"));

        return $this;
    }

    /**
     * @return $this
     */
    private function getRequestRemoteData() {

        $this->outputData = $this->readOutput();

        $requestID = (int)$this->outputData[1][0]['pedidoid'];

        $requestURL = config('roomsApi.pedidosURL') . "/{$requestID}";

        $providersURL = config('roomsApi.providersURL');

        $this->remoteData = json_decode($this->cliente->get($requestURL)->getBody()->getContents(), true);

        $this->remoteDataProviders = collect(json_decode($this->cliente->get($providersURL)->getBody()->getContents(), true))
            ->keyBy('id')
            ->toArray();

        return $this;

    }

    /**
     * Start Outlook if it is not already running
     *
     * @return $this
     */
    private function openOutlookIfClosed() {

        if (!$this->processExists('OUTLOOK')) {

            $this->info('Outlook esta cerrado. Intentando abrir...');

            pclose(popen('start /B ' . 'OUTLOOK', "r"));

            sleep(10);

            $this->info('Outlook listo!');
        }

        return $this;

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {


        $this
            ->openOutlookIfClosed()
            ->getRequestRemoteData()
            ->triggerClientMail()
            ->triggerProvidersMail();

        $this->info('Mensaje enviado. Terminado Correctamente!');

    }
}
