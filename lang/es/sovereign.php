<?php

return [
    'navigation' => [
        'groups' => [
            'network' => 'Red de Pagos',
            'liquidity' => 'Centro de Pagos',
            'intelligence' => 'Inteligencia de Divisas',
        ],
        'pathfinder' => 'Rutas de Pago',
        'matrix' => 'Matriz de Tasas',
        'ledger' => 'Historial de Operaciones',
        'corridors' => 'Rutas de Pago',
        'methods' => 'Métodos de Pago',
        'mappings' => 'Mapeos de Proveedores',
        'pairs' => 'Pares de Divisas',
        'currencies' => 'Monedas',
        'countries' => 'Países y Mapas',
    ],
    'pathfinder' => [
        'title' => 'Navegador de Pagos',
        'description' => 'Ayuda a elegir una ruta de pago clara según tasa, disponibilidad y tiempo.',
        'form' => [
            'amount' => 'Monto de Ejecución',
            'from' => 'Moneda de Origen',
            'to' => 'Moneda de Destino',
            'calculate' => 'Buscar Rutas',
        ],
        'route' => [
            'rate' => 'Tasa',
            'spread' => 'Spread',
            'trust' => 'Nivel de Confianza',
            'capacity' => 'Volumen Disponible',
            'obs' => 'Observabilidad',
            'stress' => 'Índice de Estrés',
            'rails' => 'Métodos Soportados',
            'inbound' => 'Métodos de Entrada',
            'outbound' => 'Métodos de Salida',
        ],
    ],
    'corridors' => [
        'fields' => [
            'node' => 'Proveedor',
            'node_hint' => 'La organización o el equipo que ejecuta la transferencia.',
            'bridge' => 'Moneda Intermedia',
            'bridge_hint' => 'La moneda utilizada para completar la ruta de liquidación.',
            'tier' => 'Nivel de Confianza',
            'tier_hint' => 'Los niveles inferiores son institucionales, los niveles superiores son P2P/Sombra.',
            'sla' => 'Tiempo de Liquidación (SLA)',
            'sla_hint' => 'Tiempo garantizado hasta que el activo final llegue al destino.',
        ],
    ],
    'methods' => [
        'fields' => [
            'name' => 'Nombre del Método',
            'type' => 'Tipo de Método de Pago',
            'is_global' => 'Disponibilidad Global',
            'is_global_hint' => 'Si se activa, este método estará disponible para todos los pares de divisas por defecto.',
        ],
    ],
];
