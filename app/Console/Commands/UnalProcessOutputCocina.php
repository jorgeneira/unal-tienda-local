<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Excel;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UnalProcessOutputCocina extends Command {

    private $emailClientData = [];
    private $emailProvidersData = [];
    private $outputData = [];
    private $remoteDataProviders = [];
    private $remoteData = [];
    private $wordProcess;
    private $productos;

    /**
     * @var Client
     */
    protected $cliente;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unal:processOutputCocina';

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
        $data = Excel::load(config('kitchensApi.datosSalidaPath'));

        return $data->toArray();

    }

    private function crearExcel() {

        $this->closeExcelIfOpen();

        Excel::create('datosCotizacion', function ($excel) {


            foreach($this->emailClientData as $hoja => $datos){

                /** @var \Maatwebsite\Excel\Readers\LaravelExcelReader $excel */
                $excel->sheet($hoja, function ($sheet) use($datos) {


                    /** @var \Maatwebsite\Excel\Classes\LaravelExcelWorksheet $sheet */
                    $sheet->fromArray($datos);

                });

            }


        })->store('xlsx', storage_path('macros/emails/excel'));

    }


    private function parseName($name) {
        $names  = [];
        $name   = explode(' ', $name);
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

        $cost     = 0;

        foreach ($this->productos as $referencia => $producto) {
            $cost += $this->remoteDataProviders[$referencia]['precio'] * $producto['cantidad'];
        }

        return "$ " . number_format($cost, 2);

    }

    private function processExists($string) {

        $pNameUpper       = strtoupper($string);
        $pNameLower       = strtolower($string);
        $pNameCapitalized = ucfirst($pNameLower);

        $out = [];
        exec('tasklist | grep "' . $pNameUpper . '\|' . $pNameLower . '\|' . $pNameCapitalized . '"', $out);

        return !!count($out);

    }

    private function closeExcelIfOpen() {

        if ($this->processExists('EXCEL')) {

            $this->info('Excel esta abierto. Intentando Cerrar...');

            exec('TASKKILL /f /im excel.exe');

            $this->info('Excel cerrado!');

        }

    }

    private function createProvidersExcel() {

        $this->closeExcelIfOpen();

        Excel::create('datosProviders', function ($excel) {

            foreach ($this->emailProvidersData as $provider => $productList) {

                /** @var \Maatwebsite\Excel\Readers\LaravelExcelReader $excel */
                $excel->sheet($provider, function ($sheet) use ($productList) {


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
    private function buildProvidersData() {

        $providerData = [];

        $products = collect($this->remoteDataProviders)->groupBy('marca')->toArray();

        foreach ($products as $provider => $productList) {

            $providerData[$provider] = [];

            foreach ($productList as $pIndex => $pData) {

                $providerData[$provider][] = [

                    'nombre'   => $provider,
                    'email'    => $pData['email'],
                    'producto' => $pData['descripcion'] . ' - ' . $pData['referencia'],
                    'cantidad' => $this->productos[$pData['referencia']]['cantidad'],

                ];

            }


        }

        return $providerData;

    }

    private function getTipoCocina($tipo) {

        if ($tipo == 1) {
            return 'U';
        }

        if ($tipo == 2) {
            return 'L';
        }

        return 'I';

    }

    private function getColor($tipo) {

        if ($tipo == 1) {
            return 'Blanco';
        }

        if ($tipo == 2) {
            return 'Cafe';
        }

        return 'Rojo';

    }

    private function getEstufa($tipo) {

        if ($tipo == 1) {
            return '4 Puestos';
        }

        return '6 Puestos';

    }

    private function getMeson($tipo) {

        if ($tipo == 1) {
            return 'Meson Granito';
        }

        return 'Meson Inoxidable';

    }

    /**
     * Build the client data array to be stored in excel format
     *
     */
    private function buildClientMailData() {

        $nombres = $this->parseName($this->remoteData['client']['nombre']);
        
        $hoja1 = [];
        $hoja2 = [];

        $hoja1['idCotizacion'] = $this->remoteData['id'];
        $hoja1['nombre']       = $nombres[0];
        $hoja1['apellido']     = $nombres[1];
        $hoja1['tipo']         = "Cocina";
        $hoja1['correo']       = $this->remoteData['client']['email'];
        $hoja1['fecha']        = Carbon::parse($this->remoteData['created_at'])->addDays(5)->toFormattedDateString();
        $hoja1['total']        = $this->getTotalCost();
        $hoja1['areaMaterial'] = 0;
        $hoja1['material']     = $this->remoteData['material'] == '1' ? 'Aglomerado' : 'Madera Maciza';
        $hoja1['tipo']         = $this->getTipoCocina($this->remoteData['tipo_cocina']);
        $hoja1['color']        = $this->getColor($this->remoteData['color']);
        $hoja1['estufa']       = $this->getEstufa($this->remoteData['tipo_estufa']);
        $hoja1['meson']        = $this->getMeson($this->remoteData['meson']);
        $hoja1['extractor']    = $this->remoteData['extractor'] ? 'Si' : 'No';
        $hoja1['lavaplatos']   = $this->remoteData['tipo_lavaplatos'] === 1 ? '1 Poceta' : '2 Pocetas';
        $hoja1['manija']       = "Tipo " . $this->remoteData['manija'];

        foreach($this->productos as $referencia => $producto){
            $hoja2[] = [
              'cantidad' => $producto['cantidad'],
              'producto' => $this->remoteDataProviders[$referencia]['descripcion']." - ".$referencia,
              'valorUnitario' => '$ '.number_format($this->remoteDataProviders[$referencia]['precio'],2),
              'valorTotal' => '$ '.number_format($this->remoteDataProviders[$referencia]['precio'] * $producto['cantidad'], 2),
            ];
        }

        $hoja2[] = [
            'cantidad' => '',
            'producto' => '',
            'valorUnitario' => 'Total',
            'valorTotal' => $this->getTotalCost(),
        ];

        $this->emailClientData['Hoja1'] = [$hoja1];
        $this->emailClientData['detalles'] = $hoja2;

    }

    /**
     * @return $this
     */
    private function triggerProvidersMail() {


        $this->emailProvidersData = $this->buildProvidersData();

        $this->createProvidersExcel();

        pclose(popen('start /B ' . 'WINWORD "' . config('kitchensApi.macroEmailProviders') . '"', "r"));

        return $this;
    }

    /**
     * @return $this
     */
    private function triggerClientMail() {

        $this->buildClientMailData();

        $this->crearExcel();

        pclose(popen('start /B ' . 'WINWORD "' . config('kitchensApi.macroEmailCliente') . '"', "r"));

        return $this;
    }

    /**
     * @return $this
     */
    private function getRequestRemoteData() {

        $this->outputData = $this->readOutput();

        $this->productos = collect($this->outputData[0])->keyBy('ref');

        $requestID = (int)$this->outputData[1][0]['id'];

        $requestURL = config('kitchensApi.pedidosURL') . "/{$requestID}";

        $providersURL = config('kitchensApi.providersURL');

        $this->remoteData = json_decode($this->cliente->get($requestURL)->getBody()->getContents(), true);

        $productsRefs = array_pluck($this->outputData[0], 'ref');

        $this->remoteDataProviders = collect(json_decode($this->cliente->post($providersURL, [
            'form_params' => [
                'productos' => $productsRefs,
            ],
        ])->getBody()->getContents(), true))->toArray();

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
     * @return $this
     */
    private function closeInventorIfOpen() {

        if ($this->processExists('Inventor')) {

            $this->info('Inventor esta abierto. Intentando Cerrar...');

            exec('TASKKILL /f /im inventor.exe');

            $this->info('Inventor cerrado!');

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
            ->closeInventorIfOpen()
            ->openOutlookIfClosed()
            ->getRequestRemoteData()
            ->triggerClientMail()
            ->triggerProvidersMail();
        ;
        $this->info('Mensaje enviado. Terminado Correctamente!');

    }
}
