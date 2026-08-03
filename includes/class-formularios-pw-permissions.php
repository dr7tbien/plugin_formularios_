<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formularios_PW_Permissions — Gestiona capacidades para administradores y equipo interno.
 */
final class Formularios_PW_Permissions
{
    /**
     * grant_capability — Asigna la capacidad del plugin a administrator y equipocodepty.
     */
    public static function grant_capability(): void
    {
        $roles = array('administrator', 'equipocodepty');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role instanceof WP_Role) {
                $role->add_cap(FORMULARIOS_PW_CAPABILITY);
            }
        }
    }

    /**
     * current_user_can_manage — Indica si el usuario actual puede operar el panel interno.
     */
    public static function current_user_can_manage(): bool
    {
        return current_user_can(FORMULARIOS_PW_CAPABILITY) || current_user_can('manage_options');
    }
}
