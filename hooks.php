<?php

class hooks_importar_ventas_compras_siat extends hooks 
{
    var $module_name = "importar_ventas_compras_siat";
    
    function install_extension($check_only = true) 
    {
        error_log("Instalando extensión importar_ventas_compras_siat");
        
        if ($check_only) return true;
        
        // Crear directorios para archivos importados
        $this->create_import_directories();
        
        // Registrar enlaces de menú
        $this->create_menu_entries();
        
        return true;
    }
    
    function uninstall_extension($check_only = true) 
    {
        error_log("Desinstalando extensión importar_ventas_compras_siat");
        
        if ($check_only) return true;
        
        // Eliminar enlaces de menú
        $this->remove_menu_entries();
        
        return true;
    }
    
    function activate_extension($company, $check_only = true) 
    {
        error_log("Activando extensión importar_ventas_compras_siat para empresa: " . $company);
        
        if ($check_only) return true;
        
        // Verificar que los directorios existen
        $this->create_import_directories();
        
        // Registrar enlaces de menú
        $this->create_menu_entries();
        
        return true;
    }
    
    function deactivate_extension($company, $check_only = true) 
    {
        error_log("Desactivando extensión importar_ventas_compras_siat para empresa: " . $company);
        
        if ($check_only) return true;
        
        // Eliminar enlaces de menú
        $this->remove_menu_entries();
        
        return true;
    }
    
    
    // Crear directorios necesarios para importación
    private function create_import_directories() {
        global $path_to_root;
        
        $import_dir = $path_to_root . "/modules/importar_ventas_compras_siat/importados";
        $compras_dir = $import_dir . "/compras";
        $ventas_dir = $import_dir . "/ventas";
        $temp_dir = $path_to_root . "/tmp/csv_imports";
        
        // Crear directorio principal de importados
        if (!is_dir($import_dir)) {
            if (mkdir($import_dir, 0755, true)) {
                error_log("Directorio importados creado: " . $import_dir);
            } else {
                error_log("Error al crear directorio importados: " . $import_dir);
            }
        }
        
        // Crear directorio para compras
        if (!is_dir($compras_dir)) {
            if (mkdir($compras_dir, 0755, true)) {
                error_log("Directorio compras creado: " . $compras_dir);
            } else {
                error_log("Error al crear directorio compras: " . $compras_dir);
            }
        }
        
        // Crear directorio para ventas
        if (!is_dir($ventas_dir)) {
            if (mkdir($ventas_dir, 0755, true)) {
                error_log("Directorio ventas creado: " . $ventas_dir);
            } else {
                error_log("Error al crear directorio ventas: " . $ventas_dir);
            }
        }
        
        // Crear directorio temporal para CSV
        if (!is_dir($temp_dir)) {
            if (mkdir($temp_dir, 0755, true)) {
                error_log("Directorio temporal CSV creado: " . $temp_dir);
            } else {
                error_log("Error al crear directorio temporal CSV: " . $temp_dir);
            }
        }
        
        // Crear archivos .htaccess para seguridad
        $htaccess_content = "deny from all\n";
        file_put_contents($import_dir . "/.htaccess", $htaccess_content);
        file_put_contents($temp_dir . "/.htaccess", $htaccess_content);
    }
    
    // Crear entradas de menú
    private function create_menu_entries() {
        global $path_to_root;
        
        if (function_exists('add_menu_item')) {
            // Añadir al menú principal
            add_menu_item(_("Importar Ventas y Compras SIAT"), 
                 "SA_OPEN", 
                 "modules/importar_ventas_compras_siat/pages/importar_ventas_compras.php", 
                 "General", 
                 "Transacciones");
                 
            error_log("Entrada de menú 'Importar Ventas y Compras SIAT' añadida");
        } else {
            error_log("No se pudo añadir al menú - función add_menu_item no disponible");
        }
    }
    
    // Eliminar entradas de menú
    private function remove_menu_entries() {
        // Si FrontAccounting tiene una función para eliminar entradas de menú, úsala aquí
        if (function_exists('remove_menu_item')) {
            remove_menu_item("Importar Ventas y Compras SIAT");
        }
        
        error_log("Entradas de menú eliminadas para importar_ventas_compras_siat");
    }
    
    // Hook opcional: ejecutar después de cada login
    function post_login_check() {
        // Verificar que los directorios existen
        $this->create_import_directories();
        
        // Verificar que las tablas existen
        $this->create_database_tables();
    }
}

?>