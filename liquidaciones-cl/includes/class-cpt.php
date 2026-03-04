<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_CPT {

    public static function init() {
        add_action('init', [__CLASS__, 'register']);
    }

    public static function register() {
        // Empleados
        register_post_type('cl_empleado', [
            'labels' => [
                'name' => 'Empleados',
                'singular_name' => 'Empleado',
                'add_new' => 'Agregar empleado',
                'add_new_item' => 'Agregar empleado',
                'edit_item' => 'Editar empleado',
                'new_item' => 'Nuevo empleado',
                'view_item' => 'Ver empleado',
                'search_items' => 'Buscar empleados',
                'not_found' => 'No se encontraron empleados',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        // Períodos
        register_post_type('cl_periodo', [
            'labels' => [
                'name' => 'Períodos',
                'singular_name' => 'Período',
                'add_new' => 'Agregar período',
                'add_new_item' => 'Agregar período',
                'edit_item' => 'Editar período',
                'new_item' => 'Nuevo período',
                'view_item' => 'Ver período',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        // Liquidaciones
        register_post_type('cl_liquidacion', [
            'labels' => [
                'name' => 'Liquidaciones',
                'singular_name' => 'Liquidación',
                'add_new' => 'Agregar liquidación',
                'add_new_item' => 'Agregar liquidación',
                'edit_item' => 'Editar liquidación',
                'new_item' => 'Nueva liquidación',
                'view_item' => 'Ver liquidación',
                'search_items' => 'Buscar liquidaciones',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

}
