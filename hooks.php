<?php

class hooks_importar_ventas_compras_siat extends hooks 
{
    var $module_name = "importar_ventas_compras_siat";
    
    function install_extension($check_only = true) 
    {
        error_log("Instalando extensión importar_ventas_compras_siat");
        
        if ($check_only) return true;
        
        // Crear tablas para las facturas de compra y venta si no existen
        $this->create_database_tables();
        
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
        
        // OPCIONAL: Eliminar tablas (comentado por seguridad)
        // $this->drop_database_tables();
        
        // Eliminar enlaces de menú
        $this->remove_menu_entries();
        
        return true;
    }
    
    function activate_extension($company, $check_only = true) 
    {
        error_log("Activando extensión importar_ventas_compras_siat para empresa: " . $company);
        
        if ($check_only) return true;
        
        // Verificar que existen las tablas
        $this->create_database_tables();
        
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
    
    // Crear tablas para facturas de compra y venta
    private function create_database_tables() {
        global $db;
        
        // Tabla para facturas de compra
        $sql_compras = "CREATE TABLE IF NOT EXISTS `0_facturas_compra` (
            `compra_id` int(11) NOT NULL AUTO_INCREMENT,
            `tipo_fact` varchar(10) DEFAULT '1',
            `nit_prov` varchar(20) DEFAULT NULL,
            `razon_social` varchar(255) DEFAULT NULL,
            `nro_fact` varchar(50) DEFAULT NULL,
            `nro_pol` varchar(50) DEFAULT NULL,
            `nro_auth` varchar(50) DEFAULT NULL,
            `fecha` date DEFAULT NULL,
            `importe` decimal(15,2) DEFAULT NULL,
            `imp_ice` decimal(15,2) DEFAULT NULL,
            `imp_exc` decimal(15,2) DEFAULT NULL,
            `subtotal` decimal(15,2) DEFAULT NULL,
            `descuentos` decimal(15,2) DEFAULT NULL,
            `dbr` decimal(15,2) DEFAULT NULL,
            `imp_cred_fiscal` decimal(15,2) DEFAULT NULL,
            `cred_fiscal` decimal(15,2) DEFAULT NULL,
            `tcompra` varchar(10) DEFAULT NULL,
            `cod_control` varchar(50) DEFAULT NULL,
            `debe1` varchar(20) DEFAULT NULL,
            `debe1_dimension1` int(11) DEFAULT NULL,
            `debe1_dimension2` int(11) DEFAULT NULL,
            `debe2` varchar(20) DEFAULT NULL,
            `debe2_dimension1` int(11) DEFAULT NULL,
            `debe2_dimension2` int(11) DEFAULT NULL,
            `haber` varchar(20) DEFAULT NULL,
            `haber_dimension1` int(11) DEFAULT NULL,
            `haber_dimension2` int(11) DEFAULT NULL,
            `memo` text DEFAULT NULL,
            `nro_trans` int(11) DEFAULT NULL,
            PRIMARY KEY (`compra_id`),
            KEY `idx_fecha` (`fecha`),
            KEY `idx_nit_prov` (`nit_prov`),
            KEY `idx_nro_fact` (`nro_fact`),
            KEY `idx_nro_trans` (`nro_trans`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
        
        $result_compras = db_query($sql_compras, "Error al crear tabla 0_facturas_compra");
        
        // Tabla para facturas de venta
        $sql_ventas = "CREATE TABLE IF NOT EXISTS `0_facturas_venta` (
            `venta_id` int(11) NOT NULL AUTO_INCREMENT,
            `fecha` date DEFAULT NULL,
            `nro_fact` varchar(50) DEFAULT NULL,
            `nro_auth` varchar(50) DEFAULT NULL,
            `estado` varchar(20) DEFAULT 'ACTIVO',
            `nit` varchar(20) DEFAULT NULL,
            `razon_social` varchar(255) DEFAULT NULL,
            `importe` decimal(15,2) DEFAULT NULL,
            `imp_ice` decimal(15,2) DEFAULT NULL,
            `imp_iehd` decimal(15,2) DEFAULT NULL,
            `imp_ipj` decimal(15,2) DEFAULT NULL,
            `tasas` decimal(15,2) DEFAULT NULL,
            `otros_no_sujetos` decimal(15,2) DEFAULT NULL,
            `imp_exc` decimal(15,2) DEFAULT NULL,
            `tasa_cero` decimal(15,2) DEFAULT NULL,
            `subtotal` decimal(15,2) DEFAULT NULL,
            `descuentos` decimal(15,2) DEFAULT NULL,
            `gift_card` decimal(15,2) DEFAULT NULL,
            `dbr` decimal(15,2) DEFAULT NULL,
            `imp_deb_fiscal` decimal(15,2) DEFAULT NULL,
            `deb_fiscal` decimal(15,2) DEFAULT NULL,
            `tipo_venta` varchar(10) DEFAULT '1',
            `con_derecho_cf` varchar(5) DEFAULT 'SI',
            `estado_consolidacion` varchar(20) DEFAULT 'NORMAL',
            `cod_control` varchar(50) DEFAULT NULL,
            `debe1` varchar(20) DEFAULT NULL,
            `debe1_dimension1` int(11) DEFAULT NULL,
            `debe1_dimension2` int(11) DEFAULT NULL,
            `debe2` varchar(20) DEFAULT NULL,
            `debe2_dimension1` int(11) DEFAULT NULL,
            `debe2_dimension2` int(11) DEFAULT NULL,
            `haber1` varchar(20) DEFAULT NULL,
            `haber1_dimension1` int(11) DEFAULT NULL,
            `haber1_dimension2` int(11) DEFAULT NULL,
            `haber2` varchar(20) DEFAULT NULL,
            `haber2_dimension1` int(11) DEFAULT NULL,
            `haber2_dimension2` int(11) DEFAULT NULL,
            `haber3` varchar(20) DEFAULT NULL,
            `haber3_dimension1` int(11) DEFAULT NULL,
            `haber3_dimension2` int(11) DEFAULT NULL,
            `memo` text DEFAULT NULL,
            `nro_trans` int(11) DEFAULT NULL,
            PRIMARY KEY (`venta_id`),
            KEY `idx_fecha` (`fecha`),
            KEY `idx_nit` (`nit`),
            KEY `idx_nro_fact` (`nro_fact`),
            KEY `idx_nro_trans` (`nro_trans`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
        
        $result_ventas = db_query($sql_ventas, "Error al crear tabla 0_facturas_venta");
        
        if ($result_compras && $result_ventas) {
            error_log("Tablas 0_facturas_compra y 0_facturas_venta creadas/verificadas correctamente");
        } else {
            error_log("Error al crear una o ambas tablas (compras/ventas)");
        }
        
        return $result_compras && $result_ventas;
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
    
    // Eliminar tablas (CUIDADO: esto eliminará todos los datos)
    private function drop_database_tables() {
        $sql_compras = "DROP TABLE IF EXISTS `0_facturas_compra`";
        $sql_ventas = "DROP TABLE IF EXISTS `0_facturas_venta`";
        
        $result_compras = db_query($sql_compras, "Error al eliminar tabla 0_facturas_compra");
        $result_ventas = db_query($sql_ventas, "Error al eliminar tabla 0_facturas_venta");
        
        if ($result_compras && $result_ventas) {
            error_log("Tablas 0_facturas_compra y 0_facturas_venta eliminadas");
        }
        
        return $result_compras && $result_ventas;
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