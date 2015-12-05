<?php

return [
    //'pedidosURL' => 'http://tienda.unaldev.co/api/cold-room-requests'

    'providersURL'        => 'http://remote.unal/api/providers/kitchen',
    'pedidosURL'          => 'http://remote.unal/api/kitchen-requests',

    'datosSalidaPath'     => storage_path('macros/cocina/Ejemplo2.xlsx'),

    'macroInicial'        => storage_path('macros/cocina/Lanzador.iam'),

    'macroEmailCliente'   => storage_path('macros/emails/cotizacionCocina.docm'),
    'macroEmailProviders' => storage_path('macros/emails/proveedor.docm'),
];