<?php

return [
    'navigation' => [
        'groups' => [
            'network' => 'Red Soberana',
            'liquidity' => 'Centro de Liquidez',
            'intelligence' => 'Inteligencia de Divisas',
        ],
        'pathfinder' => 'Sovereign Pathfinder',
        'matrix' => 'Matriz de Tasas',
        'ledger' => 'Libro Mayor MDK',
        'corridors' => 'Corredores de Liquidez',
        'methods' => 'Métodos de Pago',
        'mappings' => 'Mapeos de Proveedores',
        'pairs' => 'Pares de Divisas',
        'currencies' => 'Monedas',
        'countries' => 'Países y Mapas',
    ],
    'pathfinder' => [
        'title' => 'Navegador de Liquidez',
        'description' => 'Motor de enrutamiento autónomo para navegar por liquidez fragmentada y corredores sancionados.',
        'form' => [
            'amount' => 'Monto de Ejecución',
            'from' => 'Activo de Origen',
            'to' => 'Activo de Destino',
            'calculate' => 'Buscar Rutas',
        ],
        'route' => [
            'rate' => 'Tasa',
            'spread' => 'Spread',
            'trust' => 'Nivel de Confianza',
            'capacity' => 'Liquidez Disponible',
            'obs' => 'Observabilidad',
            'stress' => 'Índice de Estrés',
            'rails' => 'Vías Soportadas',
            'inbound' => 'Métodos de Entrada',
            'outbound' => 'Métodos de Salida',
        ],
    ],
    'corridors' => [
        'fields' => [
            'node' => 'Nodo del Proveedor',
            'node_hint' => 'La identidad de la entidad que ejecuta la transferencia (p. ej., "Dubai OTC Desk").',
            'bridge' => 'Activo Puente',
            'bridge_hint' => 'El activo intermediario utilizado para la liquidación (generalmente USDT).',
            'tier' => 'Nivel de Confianza',
            'tier_hint' => 'Los niveles inferiores son institucionales, los niveles superiores son P2P/Sombra.',
            'sla' => 'Tiempo de Liquidación (SLA)',
            'sla_hint' => 'Tiempo garantizado hasta que el activo final llegue al destino.',
        ],
    ],
    'methods' => [
        'fields' => [
            'name' => 'Nombre del Método',
            'type' => 'Tipo de Vía de Pago',
            'is_global' => 'Disponibilidad Global',
            'is_global_hint' => 'Si se activa, esta vía estará disponible para todos los pares de divisas por defecto.',
        ],
    ],
];
