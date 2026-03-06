<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_CPT {

    public static function all_caps(): array {
        return [
            // Empleados
            'edit_cl_empleado',
            'read_cl_empleado',
            'delete_cl_empleado',
            'edit_cl_empleados',
            'edit_others_cl_empleados',
            'publish_cl_empleados',
            'read_private_cl_empleados',
            'delete_cl_empleados',
            'delete_private_cl_empleados',
            'delete_published_cl_empleados',
            'delete_others_cl_empleados',
            'edit_private_cl_empleados',
            'edit_published_cl_empleados',
            'create_cl_empleados',

            // Períodos
            'edit_cl_periodo',
            'read_cl_periodo',
            'delete_cl_periodo',
            'edit_cl_periodos',
            'edit_others_cl_periodos',
            'publish_cl_periodos',
            'read_private_cl_periodos',
            'delete_cl_periodos',
            'delete_private_cl_periodos',
            'delete_published_cl_periodos',
            'delete_others_cl_periodos',
            'edit_private_cl_periodos',
            'edit_published_cl_periodos',
            'create_cl_periodos',

            // Liquidaciones
            'edit_cl_liquidacion',
            'read_cl_liquidacion',
            'delete_cl_liquidacion',
            'edit_cl_liquidaciones',
            'edit_others_cl_liquidaciones',
            'publish_cl_liquidaciones',
            'read_private_cl_liquidaciones',
            'delete_cl_liquidaciones',
            'delete_private_cl_liquidaciones',
            'delete_published_cl_liquidaciones',
            'delete_others_cl_liquidaciones',
            'edit_private_cl_liquidaciones',
            'edit_published_cl_liquidaciones',
            'create_cl_liquidaciones',
        ];
    }

    private static function caps_for(string $singular, string $plural): array {
        return [
            'edit_post'              => 'edit_' . $singular,
            'read_post'              => 'read_' . $singular,
            'delete_post'            => 'delete_' . $singular,
            'edit_posts'             => 'edit_' . $plural,
            'edit_others_posts'      => 'edit_others_' . $plural,
            'publish_posts'          => 'publish_' . $plural,
            'read_private_posts'     => 'read_private_' . $plural,
            'delete_posts'           => 'delete_' . $plural,
            'delete_private_posts'   => 'delete_private_' . $plural,
            'delete_published_posts' => 'delete_published_' . $plural,
            'delete_others_posts'    => 'delete_others_' . $plural,
            'edit_private_posts'     => 'edit_private_' . $plural,
            'edit_published_posts'   => 'edit_published_' . $plural,
            'create_posts'           => 'create_' . $plural,
        ];
    }

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
            'capability_type' => ['cl_empleado', 'cl_empleados'],
            'capabilities' => self::caps_for('cl_empleado', 'cl_empleados'),
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
            'capability_type' => ['cl_periodo', 'cl_periodos'],
            'capabilities' => self::caps_for('cl_periodo', 'cl_periodos'),
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
            'capability_type' => ['cl_liquidacion', 'cl_liquidaciones'],
            'capabilities' => self::caps_for('cl_liquidacion', 'cl_liquidaciones'),
            'map_meta_cap' => true,
        ]);
    }

}
