<?php

return [
    //'pedidosURL' => 'http://tienda.unaldev.co/api/cold-room-requests'

    'providersURL'        => 'http://remote.unal/api/providers/cold-room',
    'pedidosURL'          => 'http://remote.unal/api/cold-room-requests',

    'datosSalidaPath'     => storage_path('macros/cuarto/Datos de salida.xlsx'),

    'macroInicial'        => storage_path('macros\cuarto\Hoja proyecto.xlsm'),
    'macroEmailCliente'   => storage_path('macros\emails\cotizacion.docm'),
    'macroEmailProviders' => storage_path('macros\emails\proveedor.docm'),
];