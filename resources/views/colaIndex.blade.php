<!DOCTYPE html>
<html>
<head>
    <title>Tienda [Local]</title>
    <link rel="stylesheet" href="{{ asset('build/css/all.css') }}">
</head>
<body>

<div id="mainWrapper" class="container" style="margin-top: 20px">
    <div class="row">
        <div class="col col-xs-12">
            <header>
                <h1>Cola Pedidos</h1>
                <hr>
            </header>
            <div class="row">
                <div class="col col-xs-12 col-md-6 center-block" style="float: none;">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Producto</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($cola as $pedido)
                                <tr>
                                    <td>{{ $pedido['id'] }}</td>
                                    <td>{{ $pedido['nombre'] }}</td>
                                    <td>{{ $pedido['email'] }}</td>
                                    <td>{{ $pedido['producto'] }}</td>
                                </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{ asset('build/js/all.js') }}"></script>

@if($mensaje = Session::get('mensaje'))

    <script type="text/javascript">

        jQuery(document).ready(function () {
            swal("{{$mensaje}}", "", "success")
        });

    </script>

@endif

</body>
</html>
