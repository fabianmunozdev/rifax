<?php

return [
    'column_manager' => [
        'position' => 'Posición :position de :count.',
        'position_in_group' => 'Posición :position de :count en :group.',
        'reordered' => ':column movida a la posición :position de :count.',
        'reordered_deferred' => ':column movida a la posición :position de :count. Aplica las columnas para guardar.',
        'reordered_in_group' => ':column movida a la posición :position de :count en :group.',
        'reordered_in_group_deferred' => ':column movida a la posición :position de :count en :group. Aplica las columnas para guardar.',
        'actions' => ['move_up' => ['label' => 'Subir :column'], 'move_down' => ['label' => 'Bajar :column']],
    ],
    'columns' => ['editable' => [
        'saving' => 'Guardando cambio...',
        'saved' => 'Cambio guardado.',
        'failure' => 'No se pudo confirmar el guardado. Recarga la página antes de intentarlo de nuevo.',
        'unavailable' => 'Este elemento ya no se puede editar.',
    ]],
    'actions' => ['move_record_up' => ['label' => 'Subir :label'], 'move_record_down' => ['label' => 'Bajar :label']],
    'reordering' => ['position' => ':label, posición :position de :count.', 'moved' => ':label movido a la posición :position de :count.'],
];
