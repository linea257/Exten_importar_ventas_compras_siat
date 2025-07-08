<?php
/* ===========================================================================
 * importar_compras_ventas.php – versión 100 % funcional con SECCIONES
 * =========================================================================*/
$page_security = 'SA_OPEN';
$path_to_root  = "..";

/* ─── includes de FrontAccounting ─── */
include($path_to_root . "/includes/db_pager.inc");
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui/items_cart.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/ui/gl_journal_ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/gl/includes/gl_ui.inc");
include_once($path_to_root . "/includes/references.inc");
include_once($path_to_root . "/includes/connect_db_mysqli.inc");

/* ─── helpers menores ─── */
if (!function_exists('hidden')) {
    function hidden($n,$v){ return "<input type='hidden' name='".htmlspecialchars($n)."' value='".htmlspecialchars($v)."'>"; }
}
function convert_to_iso($t){ return (mb_detect_encoding($t,'UTF-8',true))?mb_convert_encoding($t,'ISO-8859-1','UTF-8'):$t; }

// Función para limpiar caracteres problemáticos
function clean_text_for_display($text) {
    $char_map = [
        'CÓD.' => 'COD.',
        'CÓDIGO' => 'CODIGO', 
        'AUTORIZACIÓN' => 'AUTORIZACION',
        'RAZÓN' => 'RAZON',
        'CRÉDITO' => 'CREDITO',
        'DÉBITO' => 'DEBITO',
        'Ó' => 'O',
        'É' => 'E',
        'Í' => 'I',
        'Á' => 'A',
        'Ú' => 'U',
        'Ñ' => 'N'
    ];
    
    return str_replace(array_keys($char_map), array_values($char_map), $text);
}

/* ==========================================================
 * 1. Cabeceras VENTAS y COMPRAS  (display + internas)
 * ==========================================================*/

/* ventas cabecera INTERNA para procesamiento completo */
$sales_internal_headers = [
   "FECHA_DE_LA_FACTURA","NRO_DE_LA_FACTURA","CODIGO_DE_AUTORIZACION",
   "NIT_CI_CLIENTE","COMPLEMENTO","NOMBRE_O_RAZON_SOCIAL",
   "IMPORTE_TOTAL_DE_LA_VENTA","IMPORTE_ICE","IMPORTE_IEHD","IMPORTE_IPJ","TASAS",
   "OTROS_NO_SUJETOS_AL_IVA","EXPORTACIONES_Y_OPERACIONES_EXENTAS",
   "VENTAS_GRAVADAS_A_TASA_CERO","SUBTOTAL","DESCUENTOS_BONIFICACIONES_REBAJAS",
   "IMPORTE_GIFT_CARD","IMPORTE_BASE_PARA_DEBITO_FISCAL","DEBITO_FISCAL",
   "ESTADO","CODIGO_DE_CONTROL","TIPO_DE_VENTA","CON_DERECHO_A_CREDITO_FISCAL",
   "ESTADO_CONSOLIDACION",
   "CUENTA_UNO_DEBE","CUENTA_DOS_DEBE","CUENTA_UNO_HABER","CUENTA_DOS_HABER","CUENTA_TRES_HABER",
   "GLOSA"
];

/* ventas cabecera DISPLAY - solo campos visibles */
$sales_display_headers = [
   "FECHA FACTURA","NRO FACTURA","COD. AUTORIZACION","NIT/CI CLIENTE",
   "RAZON SOCIAL","TOTAL VENTA",
   "SUBTOTAL","DESCUENTOS",
   "BASE DF","DEBITO FISCAL","ESTADO","COD. CONTROL",
   "Cuenta uno del DEBE","Cuenta dos del DEBE - Imp. Trans. Gasto",
   "Cuenta uno del HABER - Ventas","Cuenta dos del HABER - Debito fiscal",
   "Cuenta tres del HABER - Impto. Trans. Pasivo",
   "GLOSA"
];

/* ventas cabecera INTERNA SIMPLIFICADA - para JavaScript */
$sales_internal_headers_simplified = [
   "FECHA_DE_LA_FACTURA","NRO_DE_LA_FACTURA","CODIGO_DE_AUTORIZACION","NIT_CI_CLIENTE",
   "NOMBRE_O_RAZON_SOCIAL","IMPORTE_TOTAL_DE_LA_VENTA",
   "SUBTOTAL","DESCUENTOS_BONIFICACIONES_REBAJAS",
   "IMPORTE_BASE_PARA_DEBITO_FISCAL","DEBITO_FISCAL","ESTADO","CODIGO_DE_CONTROL",
   "CUENTA_UNO_DEBE","CUENTA_DOS_DEBE","CUENTA_UNO_HABER","CUENTA_DOS_HABER","CUENTA_TRES_HABER",
   "GLOSA"
];



/* compras cabecera DISPLAY - campos visibles SIMPLIFICADOS */
$purchases_display_headers = [
   "FECHA","NRO FACTURA","NRO DUI/DIM","NRO AUTORIZ.","COD. CONTROL",
   "NIT PROVEEDOR","RAZON SOCIAL","MONTO TOTAL",
   "SUBTOTAL","DESCUENTOS",
   "BASE CF","CRED. FISCAL","TIPO COMPRA",
   "Cuenta uno DEBE","Cuenta DEBE CF","Cuenta HABER",
   "Glosa"
];

/* compras cabecera INTERNA SIMPLIFICADA - para JavaScript */
$purchases_internal_headers = [
   "FECHA","NRO_FACTURA","NRO_POL","NRO_AUTH","CODIGO_DE_CONTROL",
   "NIT_PROVEEDOR","NOMBRE_PROVEEDOR","MONTO_TOTAL",
   "SUBTOTAL","DESCUENTOS",
   "BASE_CF","CREDITO_FISCAL","TIPO_COMPRA",
   "CUENTA_DEBE","CUENTA_CREDITO_FISCAL","CUENTA_HABER",
   "GLOSA"
];


/* ==========================================================
 * CONFIGURACION DE BASE DE DATOS PARA ISO-8859-1
 * ==========================================================*/
function configure_db_for_iso() {
    // Configurar la conexión para ISO-8859-1 (compatibilidad con FrontAccounting)
    db_query("SET NAMES latin1", "Error al configurar charset latin1");
    db_query("SET CHARACTER SET latin1", "Error al configurar character set latin1");
    db_query("SET character_set_connection=latin1", "Error al configurar connection charset latin1");
    db_query("SET character_set_results=latin1", "Error al configurar results charset latin1");
    db_query("SET character_set_client=latin1", "Error al configurar client charset latin1");
}

/* ==========================================================
 * 2. Parseador de CSV MODIFICADO para compras simplificadas
 * ==========================================================*/
function parse_siat_csv_data($filePath,$type){
    $h=@fopen($filePath,"r"); if(!$h) return null;
    $csvHead=array_map('trim',str_getcsv(fgets($h),','));
    $rows=[];
    while(($l=fgets($h))!==false){
        $l=trim($l); if(!$l) continue;
        $r=array_combine($csvHead,str_getcsv($l,',')); if(!$r) continue;

        /* --- COMPRAS SIMPLIFICADAS --- */
        if($type==='compras'){
            // Leer todos los campos del CSV
            $csv_data = [];
            $csv_data['FECHA']                   = $r['FECHA DE FACTURA/DUI/DIM']        ??'';
            $csv_data['NRO_FACTURA']             = $r['NUMERO FACTURA']                   ??'';
            $csv_data['NRO_POL']                 = $r['NUMERO DUI/DIM']                   ??'';
            $csv_data['NRO_AUTH']                = $r['CODIGO DE AUTORIZACION']           ??'';
            $csv_data['CODIGO_DE_CONTROL']       = $r['CODIGO DE CONTROL']                ??'';
            $csv_data['NIT_PROVEEDOR']           = $r['NIT PROVEEDOR']                    ??'';
            $csv_data['NOMBRE_PROVEEDOR']        = $r['RAZON SOCIAL PROVEEDOR']           ??'';
            $csv_data['MONTO_TOTAL']             = $r['IMPORTE TOTAL COMPRA']             ??'';
            $csv_data['SUBTOTAL']                = $r['SUBTOTAL']                         ??'';
            $csv_data['DESCUENTOS']              = $r['DESCUENTOS/BONIFICACIONES/REBAJAS SUJETAS AL IVA']??'';
            $csv_data['BASE_CF']                 = $r['IMPORTE BASE CF']                  ??'';
            $csv_data['CREDITO_FISCAL']          = $r['CREDITO FISCAL']                   ??'';
            $csv_data['TIPO_COMPRA']             = $r['TIPO COMPRA']                      ??'';

            // Solo retornar los campos simplificados
            $d = [];
            $d['FECHA'] = $csv_data['FECHA'];
            $d['NRO_FACTURA'] = $csv_data['NRO_FACTURA'];
            $d['NRO_POL'] = $csv_data['NRO_POL'];
            $d['NRO_AUTH'] = $csv_data['NRO_AUTH'];
            $d['CODIGO_DE_CONTROL'] = $csv_data['CODIGO_DE_CONTROL'];
            $d['NIT_PROVEEDOR'] = $csv_data['NIT_PROVEEDOR'];
            $d['NOMBRE_PROVEEDOR'] = $csv_data['NOMBRE_PROVEEDOR'];
            $d['MONTO_TOTAL'] = $csv_data['MONTO_TOTAL'];
            $d['SUBTOTAL'] = $csv_data['SUBTOTAL'];
            $d['DESCUENTOS'] = $csv_data['DESCUENTOS'];
            $d['BASE_CF'] = $csv_data['BASE_CF'];
            $d['CREDITO_FISCAL'] = $csv_data['CREDITO_FISCAL'];
            $d['TIPO_COMPRA'] = $csv_data['TIPO_COMPRA'];
            $d['CUENTA_DEBE'] = '';
            $d['CUENTA_CREDITO_FISCAL'] = '';
            $d['CUENTA_HABER'] = '';

            $monto_formateado = number_format($d['MONTO_TOTAL'], 2, '.', ',');
            $d['GLOSA'] = "Por el registro de la factura de " . $d['NOMBRE_PROVEEDOR'] 
                        . " Nro. " . $d['NRO_FACTURA'] 
                        . " de fecha " . $d['FECHA'] 
                        . " por un importe de Bs " . $monto_formateado;

            $rows[]=$d;
        }
        /* --- VENTAS SIMPLIFICADAS (sin cambios) --- */
        elseif($type==='ventas'){
            // Leer todos los campos del CSV
            $csv_data = [];
            $csv_data['FECHA_DE_LA_FACTURA']             = $r['FECHA DE LA FACTURA']                       ??'';
            $csv_data['NRO_DE_LA_FACTURA']               = $r['Nº DE LA FACTURA']                        ??'';
            $csv_data['CODIGO_DE_AUTORIZACION']          = $r['CODIGO DE AUTORIZACIÓN']                    ??'';
            $csv_data['NIT_CI_CLIENTE']                  = $r['NIT / CI CLIENTE']                        ??'';
            $csv_data['COMPLEMENTO']                     = $r['COMPLEMENTO']                             ??'';
            $csv_data['NOMBRE_O_RAZON_SOCIAL']           = $r['NOMBRE O RAZON SOCIAL']                     ??'';
            $csv_data['IMPORTE_TOTAL_DE_LA_VENTA']       = $r['IMPORTE TOTAL DE LA VENTA']                 ??'';
            $csv_data['SUBTOTAL']                        = $r['SUBTOTAL']                                ??'';
            $csv_data['DESCUENTOS_BONIFICACIONES_REBAJAS'] = $r['DESCUENTOS BONIFICACIONES Y REBAJAS SUJETAS AL IVA'] ??'';
            $csv_data['IMPORTE_BASE_PARA_DEBITO_FISCAL'] = $r['IMPORTE BASE PARA DEBITO FISCAL']           ??'';
            $csv_data['DEBITO_FISCAL']                   = $r['DEBITO FISCAL']                           ??'';
            $csv_data['ESTADO']                          = $r['ESTADO']                                  ??'';
            $csv_data['CODIGO_DE_CONTROL']               = $r['CODIGO DE CONTROL']                       ??'';

            // Solo retornar los campos simplificados
            $d = [];
            $d['FECHA_DE_LA_FACTURA'] = $csv_data['FECHA_DE_LA_FACTURA'];
            $d['NRO_DE_LA_FACTURA'] = $csv_data['NRO_DE_LA_FACTURA'];
            $d['CODIGO_DE_AUTORIZACION'] = $csv_data['CODIGO_DE_AUTORIZACION'];
            $d['NIT_CI_CLIENTE'] = $csv_data['NIT_CI_CLIENTE'];
            $d['NOMBRE_O_RAZON_SOCIAL'] = $csv_data['NOMBRE_O_RAZON_SOCIAL'];
            $d['IMPORTE_TOTAL_DE_LA_VENTA'] = $csv_data['IMPORTE_TOTAL_DE_LA_VENTA'];
            $d['SUBTOTAL'] = $csv_data['SUBTOTAL'];
            $d['DESCUENTOS_BONIFICACIONES_REBAJAS'] = $csv_data['DESCUENTOS_BONIFICACIONES_REBAJAS'];
            $d['IMPORTE_BASE_PARA_DEBITO_FISCAL'] = $csv_data['IMPORTE_BASE_PARA_DEBITO_FISCAL'];
            $d['DEBITO_FISCAL'] = $csv_data['DEBITO_FISCAL'];
            $d['ESTADO'] = $csv_data['ESTADO'];
            $d['CODIGO_DE_CONTROL'] = $csv_data['CODIGO_DE_CONTROL'];
            $d['CUENTA_UNO_DEBE'] = '';
            $d['CUENTA_DOS_DEBE'] = '';
            $d['CUENTA_UNO_HABER'] = '';
            $d['CUENTA_DOS_HABER'] = '';
            $d['CUENTA_TRES_HABER'] = '';

            $monto_formateado = number_format($d['IMPORTE_TOTAL_DE_LA_VENTA'], 2, '.', ',');
            $d['GLOSA'] = "Por el registro de la venta a " . $d['NOMBRE_O_RAZON_SOCIAL'] 
                        . " Nro. " . $d['NRO_DE_LA_FACTURA'] 
                        . " en fecha " . $d['FECHA_DE_LA_FACTURA'] 
                        . " por un importe de Bs " . $monto_formateado;

            $rows[]=$d;
        }
    }
    fclose($h);
    
    // Retornar headers correctos
    if($type==='ventas') {
        global $sales_display_headers, $sales_internal_headers_simplified;
        return [
          'headers_display'=>$sales_display_headers,
          'headers_internal'=>$sales_internal_headers_simplified,
          'data'=>$rows
        ];
    } else {
        global $purchases_display_headers, $purchases_internal_headers;
        return [
          'headers_display'=>$purchases_display_headers,
          'headers_internal'=>$purchases_internal_headers,
          'data'=>$rows
        ];
    }
}


/* ==========================================================
 * 3. Opciones de cuentas (VERSIÓN FINAL USANDO EL FRAMEWORK)
 * ==========================================================*/
function get_account_options(){
    // Iniciar el array con la opción por defecto.
    $options = ['' => "Seleccionar..."];

    set_global_connection();

    $sql = "SELECT account_code, account_name FROM `0_chart_master` WHERE inactive=0 ORDER BY account_code";

    $result = db_query($sql, "No se pudieron obtener las cuentas contables");

    if (db_num_rows($result) > 0) {
        // Usar db_fetch_assoc() de FrontAccounting para obtener cada fila.
        while ($row = db_fetch_assoc($result)) {
            // Añadir la cuenta al array de opciones.
            $options[$row['account_code']] = $row['account_code'] . ' - ' . $row['account_name'];
        }
    }

    return $options;
}
/* ==========================================================
 * 4. Handler AJAX “process_csv”
 * ==========================================================*/
if(isset($_POST['action']) && $_POST['action']==='process_csv'){
    ob_clean(); header('Content-Type: application/json; charset=UTF-8');
    $out=['status'=>'error','message'=>'Error desconocido.'];
    try{
        if(!isset($_FILES['csv_file'])||$_FILES['csv_file']['error']!=UPLOAD_ERR_OK)
            throw new Exception('Archivo CSV no recibido.');
        // ¡IMPORTANTE! El tipo ahora viene del radio button que manipulamos con JS
        $import_type=$_POST['import_type']??'';
        if(!in_array($import_type,['ventas','compras'])) throw new Exception('Tipo inválido.');
        $tmp=$_FILES['csv_file']['tmp_name'];
        $dir=$path_to_root."/tmp/csv_imports/";
        if(!file_exists($dir)&&!@mkdir($dir,0755,true)) throw new Exception('No se pudo crear dir tmp.');
        $dest=$dir.uniqid($import_type."_").".csv";
        move_uploaded_file($tmp,$dest) or throw new Exception('No se pudo mover archivo.');
        $parsed=parse_siat_csv_data($dest,$import_type);
        if(!$parsed) throw new Exception('Error al procesar CSV.');

        $out=[
          'status'=>'success',
          'headers_display'=>$parsed['headers_display'],
          'headers_internal'=>$parsed['headers_internal'],
          'data'=>$parsed['data'],
          'import_type'=>$import_type,
          'account_options'=>get_account_options()
        ];
    }catch(Exception $e){ $out=['status'=>'error','message'=>$e->getMessage()]; }
    echo json_encode($out); exit;
}

// Página principal
page(_("COMPRAS Y VENTAS SIAT"));

echo '<link rel="stylesheet" type="text/css" href="'.$path_to_root.'/css/datatables.min.css">';
echo '<script type="text/javascript" src="../js/jquery.js"></script>';
echo '<script type="text/javascript" src="../js/datatables.min.js"></script>';
echo '<script type="text/javascript" src="../js/dataTablesEspanol.js"></script>';
echo '<script type="text/javascript" src="../js/encodingFix.js"></script>';
echo '<script type="text/javascript" src="../js/importar_compras.js"></script>';
echo '<script type="text/javascript" src="../js/importar_ventas.js"></script>';
echo '<script type="text/javascript" src="../js/selectorColumna.js"></script>';
echo '<script type="text/javascript" src="../js/gestion_secciones.js"></script>';


?>

<style>
    /* --- Estilos existentes (sin cambios) --- */
  body{font-family:sans-serif;margin:0;background-color:#fff}
    #main_div_manual_container{max-width:1200px;margin:20px auto;padding:20px;background-color:#fff;border:1px solid #ddd;box-shadow:0 0 10px rgba(0,0,0,0.1)}
    h2{text-align:center;color:#333}
    #main_div{padding:15px;font-size:0.9em}
    .import-controls{margin-bottom:15px;padding:10px;border:1px solid #ccc;border-radius:4px;background-color:#f9f9f9}
    .import-controls label,.import-controls input[type="radio"],.import-controls input[type="file"],.import-controls button{vertical-align:middle}
    #table_container{width:100%;overflow-x:auto;margin-top:15px;border:1px solid #ccc}
    #siat_table, #compras_contabilizadas_table, #ventas_contabilizadas_table {width:100%!important;font-size:0.9em;border-collapse:collapse}
    .dataTables_wrapper .dataTables_length,.dataTables_wrapper .dataTables_filter,.dataTables_wrapper .dataTables_info,.dataTables_wrapper .dataTables_paginate{margin-bottom:8px;font-size:0.95em;padding:5px}
    .dataTables_scrollHeadInner table.dataTable{margin-bottom:0!important}
    #loader{display:none;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;margin:15px auto}
    @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    #siat_table th,#siat_table td, #compras_contabilizadas_table th, #compras_contabilizadas_table td, #ventas_contabilizadas_table th, #ventas_contabilizadas_table td{white-space:nowrap;padding:6px 10px;border:1px solid #e0e0e0;text-align:left;}
    #siat_table thead th, #compras_contabilizadas_table thead th, #ventas_contabilizadas_table thead th{background-color:#f1f1f1;font-weight:bold}
    #siat_table tbody td:not(:first-child){cursor:pointer}
    #compras_contabilizadas_table tbody td:not(:first-child){cursor:pointer}
    #ventas_contabilizadas_table tbody td:not(:first-child){cursor:pointer}
    #siat_table tbody td[contenteditable=true]:focus{background-color:#fff3cd;outline:none}
    #compras_contabilizadas_table tbody td[contenteditable=true]:focus{background-color:#fff3cd;outline:none}
    #ventas_contabilizadas_table tbody td[contenteditable=true]:focus{background-color:#fff3cd;outline:none}
    .import-controls button{padding:6px 12px;font-size:0.9em;cursor:pointer;background-color:#5cb85c;color:white;border:1px solid #4cae4c;border-radius:4px}
    .import-controls button#clear_table_button{background-color:#f0ad4e;border-color:#eea236}
    div#siat_table_info{margin-top:10px;margin-bottom:10px;padding-left:10px}
    .dt-controls-top,.dt-controls-bottom{overflow:hidden}
    .dt-controls-top{margin-bottom:10px}
    .dt-controls-top .dataTables_filter{float:right;text-align:right}
    .dt-controls-bottom{margin-top:10px}
    .dt-controls-bottom .dataTables_info{float:left}
    .dt-controls-bottom .dataTables_length{float:right;margin-left:15px}
    .dt-controls-bottom .dataTables_paginate{float:right}
    .dataTables_filter input{width:auto;margin-left:0.5em}
    .dataTables_length label{margin-right:10px}
    .custom-footer{display:flex;flex-direction:row-reverse;justify-content:space-between;align-items:center;padding-right:10px;padding-bottom:10px;margin-top:10px;}
    .dt-length:nth-of-type(3){position:relative;bottom:10px}
    .dt-search{padding:10px 0 10px 10px}
    .check_vc{display:flex;flex-direction:row;align-items:center;margin-bottom:15px;gap:20px}
    .dt-column-order::before,.dt-column-order::after{content:none!important}
    .dt-column-order{background-image:url('../images/sort_both.png');background-repeat:no-repeat;background-position:center right;background-size:17px 17px;padding-right:20px}
    .dt-column-order[aria-label*="invert sorting"]{background-image:url('../images/sort_asc.png')}
    .dt-column-order[aria-label*="remove sorting"]{background-image:url('../images/sort_desc.png')}
    input#import_type_ventas,input#import_type_compras{position:relative;right:-4px;bottom:3px;}
    .account-select { width: 100%; max-width: 140px; min-width: 100px; padding: 2px 4px; font-size: 0.8em; border: 1px solid #ccc; border-radius: 3px; background-color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .account-select:focus { outline: none; border-color: #007cba; box-shadow: 0 0 3px rgba(0, 124, 186, 0.3); }
    .global-controls-row { background-color: #e8f4fd !important; border: 2px solid #007cba !important; cursor: default !important; }
    .global-controls-row td { background-color: #e8f4fd !important; padding: 6px 8px !important; vertical-align: middle; cursor: default !important; }
    .global-controls-row .account-select { border: 2px solid #007cba; background-color: white; max-width: 120px; font-size: 0.75em; cursor: pointer !important; }
    .global-controls-row .global-checkbox { margin-right: 3px; transform: scale(1.1); vertical-align: middle; cursor: pointer !important; width: 16px; height: 16px; accent-color: #007cba; }
    .global-checkbox-cell { text-align: center; min-width: 50px; }
    .global-controls-row .non-account-cell { background-color: #e8f4fd !important; border: 1px solid #007cba; cursor: default !important; }
    .global-controls-row:hover { background-color: #e8f4fd !important; }
    .global-controls-row td:hover { background-color: #e8f4fd !important; }
    input[type="checkbox"].global-checkbox { -webkit-appearance: checkbox; -moz-appearance: checkbox; appearance: checkbox; display: inline-block; position: relative; cursor: pointer; }
    input[type="checkbox"].global-checkbox:checked { background-color: #007cba; border-color:rgba(0, 124, 186, 0.63); }
    .contabilizar-btn, .contabilizar-ventas-btn { background-color: #5cb85c; color: white; border: 1px solid #4cae4c; border-radius: 4px; padding: 4px 8px; font-size: 0.8em; cursor: pointer; transition: background-color 0.3s; }
    .contabilizar-btn:hover, .contabilizar-ventas-btn:hover { background-color: #449d44; }
    .contabilizar-btn:disabled, .contabilizar-ventas-btn:disabled { background-color: #ccc; cursor: not-allowed; }
    .ver-asiento-btn { background-color: #5bc0de; color: white; border: 1px solid #46b8da; border-radius: 4px; padding: 4px 8px; font-size: 0.8em; cursor: pointer; transition: background-color 0.3s; }
    .ver-asiento-btn:hover { background-color: #31b0d5; }

    /* --- NUEVOS ESTILOS PARA LAS PESTAÑAS --- */
    .tabs-container { border-bottom: 2px solid #ccc; margin-bottom: 15px; }
    .tab-link { background-color: #f1f1f1; border: 1px solid #ccc; border-bottom: none; padding: 10px 15px; cursor: pointer; font-size: 1.1em; margin-right: 5px; border-radius: 5px 5px 0 0; position: relative; bottom: -2px; color: #555; }
    .tab-link.active { background-color: #fff; border-bottom: 2px solid #fff; font-weight: bold; color: #000; }
    .tab-link:not(.active):hover { background-color: #e2e2e2; }

    /* Estilos para celdas de solo lectura en la tabla de historial */
    #compras_section_container{ width: 100%; border: 1px solid #ccc;}
    #compras_contabilizadas_table tbody tr:nth-child(even) { background-color: #f9f9f9; }
    #compras_contabilizadas_table tbody tr:hover { background-color: #f1f1f1; }
    #ventas_section_container{ width: 100%; border: 1px solid #ccc;}
    #ventas_contabilizadas_table tbody tr:nth-child(even) { background-color: #f9f9f9; }
    #ventas_contabilizadas_table tbody tr:hover { background-color: #f1f1f1; }
    .text-right { text-align: right; }
    .glosa-cell { max-width: 350px; text-overflow: ellipsis; overflow: hidden; }
    .dt-search{padding: 0; margin-left: 5px; margin-top: 10px; margin-bottom: 10px;}
    .dt-info{ display: none;}

    /*estilos para botones de tablas contabilizadas */
 /*estilos para botones de tablas contabilizadas */
    #compras_tab_content, #ventas_tab_content {
        margin-top: 20px;
    }
    
    /* Asegurar que las tablas contabilizadas estén ocultas inicialmente */
    #compras_section_container, #ventas_section_container {
        display: none;
    }
    
    /* Asegurar que solo ventas esté visible por defecto */
    #compras_tab_content {
        display: none ;
    }
    
    #ventas_tab_content {
        display: block;
    }
    
    .toggle-container { 
        margin: 20px 0 10px 0; 
        display: flex; 
        justify-content: flex-start; 
        align-items: center; 
    }
    
    .toggle-btn { 
        background-color: #f8f9fa; 
        border: 1px solid #dee2e6; 
        border-radius: 6px; 
        padding: 8px 16px; 
        font-size: 14px; 
        font-weight: 500; 
        color: #495057; 
        cursor: pointer; 
        transition: all 0.2s ease-in-out; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        min-width: 200px; 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); 
    }
    
    .toggle-btn:hover { 
        background-color: #e9ecef; 
        border-color: #adb5bd; 
        color: #212529; 
        transform: translateY(-1px); 
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); 
    }
    
    .toggle-btn:active { 
        transform: translateY(0); 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); 
    }
    
    .toggle-btn.active { 
        background-color: #007bff; 
        border-color: #007bff; 
        color: white; 
    }
    
    .toggle-btn.active:hover { 
        background-color: #0056b3; 
        border-color: #004085; 
        color: white; 
    }
    
    .toggle-icon { 
        font-size: 16px; 
        font-weight: bold; 
        min-width: 16px; 
        text-align: center; 
        line-height: 1; 
        transition: transform 0.2s ease-in-out; 
    }
    
    .toggle-btn.active .toggle-icon { 
        transform: rotate(0deg); 
    }
    
    .toggle-text { 
        font-size: 13px; 
        white-space: nowrap; 
        user-select: none; 
    }
    
    .table-container { 
        overflow: hidden; 
    }
    
    @media (max-width: 768px) { 
        .toggle-btn { 
            min-width: auto; 
            padding: 6px 12px; 
            font-size: 13px; 
        } 
        .toggle-text { 
            font-size: 12px; 
        } 
        .toggle-icon { 
            font-size: 14px; 
        } 
    }
    
    .toggle-btn:focus { 
        outline: none; 
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); 
    }
    
    .toggle-btn:focus:not(:focus-visible) { 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); 
    }
    
    .toggle-container:has(#toggle_compras_btn) { 
        border-left: 3px solid #28a745; 
        padding-left: 12px; 
    }
    
    .toggle-container:has(#toggle_ventas_btn) { 
        border-left: 3px solid #17a2b8; 
        padding-left: 12px; 
    }
    
    .toggle-btn.active .toggle-icon { 
        text-shadow: 0 0 3px rgba(255, 255, 255, 0.3); 
    }
    
    .toggle-btn.loading { 
        pointer-events: none; 
        opacity: 0.6; 
    }
    
    .toggle-btn.loading .toggle-icon { 
        animation: spin 1s linear infinite; 
    }
    
    @keyframes spin { 
        from { transform: rotate(0deg); } 
        to { transform: rotate(360deg); } 
    }
    

</style>

<?php
start_form(true, true);
div_start('main_div');

// Interfaz de Importación de CSV con Pestañas
?>

<div class="tabs-container">
    <button class="tab-link active" data-tab="ventas"><?php echo _("Importar Ventas"); ?></button>
    <button class="tab-link" data-tab="compras"><?php echo _("Importar Compras"); ?></button>
</div>

<div class="import-controls">
    <div class="check_vc" style="display: none;">
        <label><input type="radio" id="import_type_ventas" name="import_type_radio" value="ventas" checked> Ventas</label>
        <label><input type="radio" id="import_type_compras" name="import_type_radio" value="compras"> Compras</label>
    </div>
    
    <div>
        <label for="csv_file_input"><?php echo _("Seleccionar Archivo CSV:"); ?></label>
        <input type="file" id="csv_file_input" name="csv_file_input" accept=".csv">
    </div>
    
    <div style="margin-top: 20px;">
        <button type="button" id="process_csv_button" class="ajaxsubmit"><?php echo _("Importar y Mostrar Datos"); ?></button>
        <button type="button" id="clear_table_button"><?php echo _("Limpiar Tabla y Filtros"); ?></button>
    </div>
</div>

<div id="loader" style="display:none;">
    <img src="<?php echo "$path_to_root/themes/" . user_theme() . "/images/ajax-loader.gif"; ?>" alt="Loading...">
    <br>
</div>

<div id="table_container">
    <table id="siat_table" class="display" style="width:100%">
        <thead>
            <tr>
                <th><?php echo _("Cargue un archivo CSV para mostrar los registros"); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2"><?php echo _("Esperando datos..."); ?></td>
            </tr>
        </tbody>
    </table>
</div>


<?php
/* ==========================================================
 * FUNCIONES PARA OBTENER DATOS CON NOMBRES DE CUENTAS
 * ==========================================================*/

/* Función para formatear cuenta con nombre */
function format_account_with_name($account_code, $account_name) {
    if (empty($account_code)) {
        return '';
    }
    
    $clean_code = clean_text_for_display($account_code);
    
    if (!empty($account_name)) {
        $clean_name = clean_text_for_display($account_name);
        return $clean_code . ' - ' . $clean_name;
    }
    
    return $clean_code;
}

/* Función para obtener datos de VENTAS con nombres de cuentas */
function get_ventas_contabilizadas_with_account_names() {
    set_global_connection();
    configure_db_for_iso();
    
    $sql = "SELECT 
                v.*,
                c1.account_name as debe1_name,
                c2.account_name as debe2_name,
                c3.account_name as haber1_name,
                c4.account_name as haber2_name,
                c5.account_name as haber3_name
            FROM `0_facturas_venta` v
            LEFT JOIN `0_chart_master` c1 ON v.debe1 = c1.account_code AND c1.inactive = 0
            LEFT JOIN `0_chart_master` c2 ON v.debe2 = c2.account_code AND c2.inactive = 0
            LEFT JOIN `0_chart_master` c3 ON v.haber1 = c3.account_code AND c3.inactive = 0
            LEFT JOIN `0_chart_master` c4 ON v.haber2 = c4.account_code AND c4.inactive = 0
            LEFT JOIN `0_chart_master` c5 ON v.haber3 = c5.account_code AND c5.inactive = 0
            ORDER BY v.fecha DESC, v.venta_id DESC";
    
    $result = db_query($sql, "Error al obtener las facturas de venta contabilizadas");
    return $result;
}

/* Función para obtener datos de COMPRAS con nombres de cuentas */
function get_compras_contabilizadas_with_account_names() {
    set_global_connection();
    configure_db_for_iso();
    
    $sql = "SELECT 
                c.*,
                c1.account_name as debe1_name,
                c2.account_name as debe2_name,
                c3.account_name as haber_name
            FROM `0_facturas_compra` c
            LEFT JOIN `0_chart_master` c1 ON c.debe1 = c1.account_code AND c1.inactive = 0
            LEFT JOIN `0_chart_master` c2 ON c.debe2 = c2.account_code AND c2.inactive = 0
            LEFT JOIN `0_chart_master` c3 ON c.haber = c3.account_code AND c3.inactive = 0
            ORDER BY c.fecha DESC, c.compra_id DESC";
    
    $result = db_query($sql, "Error al obtener las facturas de compra contabilizadas");
    return $result;
}


/* ==========================================================
 * FUNCIÓN PERSONALIZADA PARA OBTENER SOLO CAMPOS VISIBLES DE VENTAS
 * ==========================================================*/
function get_filtered_sales_data($raw_data) {
    $filtered_data = [];
    
    // Mapeo de campos internos a campos display para ventas
    $field_mapping = [
        'FECHA_DE_LA_FACTURA' => 'FECHA_DE_LA_FACTURA',
        'NRO_DE_LA_FACTURA' => 'NRO_DE_LA_FACTURA', 
        'CODIGO_DE_AUTORIZACION' => 'CODIGO_DE_AUTORIZACION',
        'NIT_CI_CLIENTE' => 'NIT_CI_CLIENTE',
        'NOMBRE_O_RAZON_SOCIAL' => 'NOMBRE_O_RAZON_SOCIAL',
        'IMPORTE_TOTAL_DE_LA_VENTA' => 'IMPORTE_TOTAL_DE_LA_VENTA',
        'SUBTOTAL' => 'SUBTOTAL',
        'DESCUENTOS_BONIFICACIONES_REBAJAS' => 'DESCUENTOS_BONIFICACIONES_REBAJAS',
        'IMPORTE_BASE_PARA_DEBITO_FISCAL' => 'IMPORTE_BASE_PARA_DEBITO_FISCAL',
        'DEBITO_FISCAL' => 'DEBITO_FISCAL',
        'ESTADO' => 'ESTADO',
        'CODIGO_DE_CONTROL' => 'CODIGO_DE_CONTROL',
        'CUENTA_UNO_DEBE' => 'CUENTA_UNO_DEBE',
        'CUENTA_DOS_DEBE' => 'CUENTA_DOS_DEBE', 
        'CUENTA_UNO_HABER' => 'CUENTA_UNO_HABER',
        'CUENTA_DOS_HABER' => 'CUENTA_DOS_HABER',
        'CUENTA_TRES_HABER' => 'CUENTA_TRES_HABER',
        'GLOSA' => 'GLOSA'
    ];
    
    foreach ($raw_data as $row) {
        $filtered_row = [];
        foreach ($field_mapping as $internal_field => $display_field) {
            $filtered_row[$display_field] = $row[$internal_field] ?? '';
        }
        $filtered_data[] = $filtered_row;
    }
    
    return $filtered_data;
}

/* ==========================================================
 * HEADERS INTERNOS SIMPLIFICADOS PARA DISPLAY DE VENTAS
 * ==========================================================*/
$sales_display_internal_headers = [
   "FECHA_DE_LA_FACTURA","NRO_DE_LA_FACTURA","CODIGO_DE_AUTORIZACION","NIT_CI_CLIENTE",
   "NOMBRE_O_RAZON_SOCIAL","IMPORTE_TOTAL_DE_LA_VENTA",
   "SUBTOTAL","DESCUENTOS_BONIFICACIONES_REBAJAS",
   "IMPORTE_BASE_PARA_DEBITO_FISCAL","DEBITO_FISCAL","ESTADO","CODIGO_DE_CONTROL",
   "CUENTA_UNO_DEBE","CUENTA_DOS_DEBE","CUENTA_UNO_HABER","CUENTA_DOS_HABER","CUENTA_TRES_HABER",
   "GLOSA"
];


?>

<!-- TABLA DE COMPRAS CONTABILIZADAS MEJORADA -->
<div id="compras_tab_content" style="display: none;">
    <?php
    // Verificar si hay datos de compras antes de mostrar el botón
    $compras_result = get_compras_contabilizadas_with_account_names();
    $has_compras_data = db_num_rows($compras_result) > 0;
    
    if ($has_compras_data): ?>
    <!-- Botón toggle para compras contabilizadas -->
    <div class="toggle-container">
        <button class="toggle-btn" id="toggle_compras_btn" data-target="compras_section_container">
            <span class="toggle-icon">+</span>
            <span class="toggle-text">Ver compras contabilizadas</span>
        </button>
    </div>

    <!-- TABLA DE COMPRAS CONTABILIZADAS -->
    <div id="compras_section_container" class="table-container" style="display: none; margin-top: 15px;">
        <table id="compras_contabilizadas_table" class="display">
            <thead>
                <tr>
                    <?php 
                    foreach ($purchases_display_headers as $header) { 
                        $clean_header = clean_text_for_display($header);
                        echo "<th>" . htmlspecialchars($clean_header, ENT_QUOTES, 'ISO-8859-1') . "</th>"; 
                    } 
                    ?>
                    <th>Asiento</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Reiniciar el cursor del resultado ya que lo usamos arriba
                $result = get_compras_contabilizadas_with_account_names();
                
                while ($row = db_fetch_assoc($result)) {
                    echo "<tr>";
                    
                    // Limpiar campos básicos
                    $fecha = clean_text_for_display($row["fecha"] ?? '');
                    $nro_fact = clean_text_for_display($row["nro_fact"] ?? '');
                    $nro_pol = clean_text_for_display($row["nro_pol"] ?? '');
                    $nro_auth = clean_text_for_display($row["nro_auth"] ?? '');
                    $cod_control = clean_text_for_display($row["cod_control"] ?? '');
                    $nit_prov = clean_text_for_display($row["nit_prov"] ?? '');
                    $razon_social = clean_text_for_display($row["razon_social"] ?? '');
                    $tcompra = clean_text_for_display($row["tcompra"] ?? '');
                    $memo = clean_text_for_display($row["memo"] ?? '');
                    
                    // FORMATEAR CUENTAS CON NOMBRES (COMPRAS: debe1, debe2, haber)
                    $debe1_formatted = format_account_with_name($row["debe1"] ?? '', $row["debe1_name"] ?? '');
                    $debe2_formatted = format_account_with_name($row["debe2"] ?? '', $row["debe2_name"] ?? '');
                    $haber_formatted = format_account_with_name($row["haber"] ?? '', $row["haber_name"] ?? '');
                    
                    // MOSTRAR SOLO LAS COLUMNAS SIMPLIFICADAS
                    echo "<td>" . htmlspecialchars($fecha, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nro_fact, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nro_pol, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nro_auth, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($cod_control, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nit_prov, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($razon_social, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='text-right'>" . number_format($row["importe"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["subtotal"] ?? $row['dbr'] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["descuentos"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["dbr"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["cred_fiscal"] ?? 0, 2) . "</td>";
                    echo "<td>" . htmlspecialchars($tcompra, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    
                    // MOSTRAR CUENTAS FORMATEADAS CON NOMBRES
                    echo "<td class='account-cell' title='" . htmlspecialchars($debe1_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($debe1_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($debe2_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($debe2_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($haber_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($haber_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    
                    echo "<td class='glosa-cell' title='" . htmlspecialchars($memo, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($memo, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td><a target='_blank' class='ver-asiento-btn' href='../gl/view/gl_trans_view.php?type_id=0&trans_no=" . ($row['nro_trans'] ?? '') . "'>Ver Asiento #" . ($row['nro_trans'] ?? '') . "</a></td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <!-- Mensaje cuando no hay datos de compras -->
    <div class="no-data-message" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
        <i class="fa fa-info-circle" style="margin-right: 8px;"></i>
        No hay facturas de compra contabilizadas para mostrar
    </div>
    <?php endif; ?>
</div>

<!-- TABLA DE VENTAS CONTABILIZADAS -->
<div id="ventas_tab_content" style="display: block;">
    <?php
    // Verificar si hay datos de ventas antes de mostrar el botón
    $ventas_result = get_ventas_contabilizadas_with_account_names();
    $has_ventas_data = db_num_rows($ventas_result) > 0;
    
    if ($has_ventas_data): ?>
    <!-- Botón toggle para ventas contabilizadas -->
    <div class="toggle-container">
        <button class="toggle-btn" id="toggle_ventas_btn" data-target="ventas_section_container">
            <span class="toggle-icon">+</span>
            <span class="toggle-text">Ver ventas contabilizadas</span>
        </button>
    </div>

    <!-- TABLA DE VENTAS CONTABILIZADAS -->
    <div id="ventas_section_container" class="table-container" style="display: none; margin-top: 15px;">
        <table id="ventas_contabilizadas_table" class="display">
            <thead>
                <tr>
                    <?php 
                    // Usar los encabezados SIMPLIFICADOS de ventas
                    foreach ($sales_display_headers as $header) { 
                        $clean_header = clean_text_for_display($header);
                        echo "<th>" . htmlspecialchars($clean_header, ENT_QUOTES, 'ISO-8859-1') . "</th>"; 
                    } 
                    ?>
                    <th>Asiento</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Reiniciar el cursor del resultado ya que lo usamos arriba
                $result = get_ventas_contabilizadas_with_account_names();
                
                while ($row = db_fetch_assoc($result)) {
                    echo "<tr>";
                    
                    // Limpiar campos básicos
                    $fecha = clean_text_for_display($row["fecha"] ?? '');
                    $nro_fact = clean_text_for_display($row["nro_fact"] ?? '');
                    $nro_auth = clean_text_for_display($row["nro_auth"] ?? '');
                    $nit = clean_text_for_display($row["nit"] ?? '');
                    $razon_social = clean_text_for_display($row["razon_social"] ?? '');
                    $estado = clean_text_for_display($row["estado"] ?? '');
                    $cod_control = clean_text_for_display($row["cod_control"] ?? '');
                    $memo = clean_text_for_display($row["memo"] ?? '');
                    
                    // FORMATEAR CUENTAS CON NOMBRES (VENTAS: debe1, debe2, haber1, haber2, haber3)
                    $debe1_formatted = format_account_with_name($row["debe1"] ?? '', $row["debe1_name"] ?? '');
                    $debe2_formatted = format_account_with_name($row["debe2"] ?? '', $row["debe2_name"] ?? '');
                    $haber1_formatted = format_account_with_name($row["haber1"] ?? '', $row["haber1_name"] ?? '');
                    $haber2_formatted = format_account_with_name($row["haber2"] ?? '', $row["haber2_name"] ?? '');
                    $haber3_formatted = format_account_with_name($row["haber3"] ?? '', $row["haber3_name"] ?? '');
                    
                    // MOSTRAR SOLO LAS COLUMNAS SIMPLIFICADAS
                    echo "<td>" . htmlspecialchars($fecha, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nro_fact, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nro_auth, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($nit, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($razon_social, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='text-right'>" . number_format($row["importe"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["subtotal"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["descuentos"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["base_df"] ?? 0, 2) . "</td>";
                    echo "<td class='text-right'>" . number_format($row["deb_fiscal"] ?? 0, 2) . "</td>";
                    echo "<td>" . htmlspecialchars($estado, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td>" . htmlspecialchars($cod_control, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    
                    // MOSTRAR CUENTAS FORMATEADAS CON NOMBRES
                    echo "<td class='account-cell' title='" . htmlspecialchars($debe1_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($debe1_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($debe2_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($debe2_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($haber1_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($haber1_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($haber2_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($haber2_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td class='account-cell' title='" . htmlspecialchars($haber3_formatted, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($haber3_formatted, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    
                    echo "<td class='glosa-cell' title='" . htmlspecialchars($memo, ENT_QUOTES, 'ISO-8859-1') . "'>" . htmlspecialchars($memo, ENT_QUOTES, 'ISO-8859-1') . "</td>";
                    echo "<td><a target='_blank' class='ver-asiento-btn' href='../gl/view/gl_trans_view.php?type_id=0&trans_no=" . ($row['nro_trans'] ?? '') . "'>Ver Asiento #" . ($row['nro_trans'] ?? '') . "</a></td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <!-- Mensaje cuando no hay datos de ventas -->
    <div class="no-data-message" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
        <i class="fa fa-info-circle" style="margin-right: 8px;"></i>
        No hay facturas de venta contabilizadas para mostrar
    </div>
    <?php endif; ?>
</div>

<?php
// Modifica la función parse_siat_csv_data para que retorne los headers corregidos:
function parse_siat_csv_data_fixed($filePath, $type) {
    $h = @fopen($filePath, "r"); 
    if (!$h) return null;
    
    $csvHead = array_map('trim', str_getcsv(fgets($h), ','));
    $rows = [];
    
    while (($l = fgets($h)) !== false) {
        $l = trim($l); 
        if (!$l) continue;
        $r = array_combine($csvHead, str_getcsv($l, ',')); 
        if (!$r) continue;

        /* --- COMPRAS --- */
        if ($type === 'compras') {
            $d = [];
            $d['FECHA'] = $r['FECHA DE FACTURA/DUI/DIM'] ?? '';
            $d['NRO_FACTURA'] = $r['NUMERO FACTURA'] ?? '';
            $d['NRO_POL'] = $r['NUMERO DUI/DIM'] ?? '';
            $d['NRO_AUTH'] = $r['CODIGO DE AUTORIZACION'] ?? '';
            $d['CODIGO_DE_CONTROL'] = $r['CODIGO DE CONTROL'] ?? '';
            $d['NIT_PROVEEDOR'] = $r['NIT PROVEEDOR'] ?? '';
            $d['NOMBRE_PROVEEDOR'] = $r['RAZON SOCIAL PROVEEDOR'] ?? '';
            $d['MONTO_TOTAL'] = $r['IMPORTE TOTAL COMPRA'] ?? '';
            $d['IMP_ICE'] = $r['IMPORTE ICE'] ?? '';
            $d['IMP_IEHD'] = $r['IMPORTE IEHD'] ?? '';
            $d['IMP_IPJ'] = $r['IMPORTE IPJ'] ?? '';
            $d['TASAS'] = $r['TASAS'] ?? '';
            $d['MONTO_NO_SUJETO_IVA'] = $r['OTRO NO SUJETO A CREDITO FISCAL'] ?? '';
            $d['IMPORTES_EXENTOS'] = $r['IMPORTES EXENTOS'] ?? '';
            $d['SUBTOTAL'] = $r['SUBTOTAL'] ?? '';
            $d['DESCUENTOS'] = $r['DESCUENTOS/BONIFICACIONES/REBAJAS SUJETAS AL IVA'] ?? '';
            $d['BASE_CF'] = $r['IMPORTE BASE CF'] ?? '';
            $d['CREDITO_FISCAL'] = $r['CREDITO FISCAL'] ?? '';
            $d['TIPO_COMPRA'] = $r['TIPO COMPRA'] ?? '';
            $d['CUENTA_DEBE'] = $d['CUENTA_CREDITO_FISCAL'] = $d['CUENTA_HABER'] = '';

            $monto_formateado = number_format($d['MONTO_TOTAL'], 2, '.', ',');
            $d['GLOSA'] = "Por el registro de la factura de " . $d['NOMBRE_PROVEEDOR'] 
                        . " Nro. " . $d['NRO_FACTURA'] 
                        . " de fecha " . $d['FECHA'] 
                        . " por un importe de Bs " . $monto_formateado;

            $rows[] = $d;
        }
        /* --- VENTAS --- */
        elseif ($type === 'ventas') {
            $d = [];
            $d['FECHA_DE_LA_FACTURA'] = $r['FECHA DE LA FACTURA'] ?? '';
            $d['NRO_DE_LA_FACTURA'] = $r['Nº DE LA FACTURA'] ?? '';
            $d['CODIGO_DE_AUTORIZACION'] = $r['CODIGO DE AUTORIZACIÓN'] ?? '';
            $d['NIT_CI_CLIENTE'] = $r['NIT / CI CLIENTE'] ?? '';
            $d['COMPLEMENTO'] = $r['COMPLEMENTO'] ?? '';
            $d['NOMBRE_O_RAZON_SOCIAL'] = $r['NOMBRE O RAZON SOCIAL'] ?? '';
            $d['IMPORTE_TOTAL_DE_LA_VENTA'] = $r['IMPORTE TOTAL DE LA VENTA'] ?? '';
            $d['IMPORTE_ICE'] = $r['IMPORTE ICE'] ?? '';
            $d['IMPORTE_IEHD'] = $r['IMPORTE IEHD'] ?? '';
            $d['IMPORTE_IPJ'] = $r['IMPORTE IPJ'] ?? '';
            $d['TASAS'] = $r['TASAS'] ?? '';
            $d['OTROS_NO_SUJETOS_AL_IVA'] = $r['OTROS NO SUJETOS AL IVA'] ?? '';
            $d['EXPORTACIONES_Y_OPERACIONES_EXENTAS'] = $r['EXPORTACIONES Y OPERACIONES EXENTAS'] ?? '';
            $d['VENTAS_GRAVADAS_A_TASA_CERO'] = $r['VENTAS GRAVADAS A TASA CERO'] ?? '';
            $d['SUBTOTAL'] = $r['SUBTOTAL'] ?? '';
            $d['DESCUENTOS_BONIFICACIONES_REBAJAS'] = $r['DESCUENTOS BONIFICACIONES Y REBAJAS SUJETAS AL IVA'] ?? '';
            $d['IMPORTE_GIFT_CARD'] = $r['IMPORTE GIFT CARD'] ?? '';
            $d['IMPORTE_BASE_PARA_DEBITO_FISCAL'] = $r['IMPORTE BASE PARA DEBITO FISCAL'] ?? '';
            $d['DEBITO_FISCAL'] = $r['DEBITO FISCAL'] ?? '';
            $d['ESTADO'] = $r['ESTADO'] ?? '';
            $d['CODIGO_DE_CONTROL'] = $r['CODIGO DE CONTROL'] ?? '';
            $d['TIPO_DE_VENTA'] = $r['TIPO DE VENTA'] ?? '';
            $d['CON_DERECHO_A_CREDITO_FISCAL'] = $r['CON DERECHO A CREDITO FISCAL'] ?? '';
            $d['ESTADO_CONSOLIDACION'] = $r['ESTADO CONSOLIDACION'] ?? '';
            
            $d['CUENTA_UNO_DEBE'] = $d['CUENTA_DOS_DEBE'] = $d['CUENTA_UNO_HABER'] = $d['CUENTA_DOS_HABER'] = $d['CUENTA_TRES_HABER'] = '';

            $monto_formateado = number_format($d['IMPORTE_TOTAL_DE_LA_VENTA'], 2, '.', ',');
            $d['GLOSA'] = "Por el registro de la venta a " . $d['NOMBRE_O_RAZON_SOCIAL'] 
                        . " Nro. " . $d['NRO_DE_LA_FACTURA'] 
                        . " en fecha " . $d['FECHA_DE_LA_FACTURA'] 
                        . " por un importe de Bs " . $monto_formateado;
            $rows[] = $d;
        }
    }
    fclose($h);
    
    global $sales_display_headers, $purchases_display_headers;
    global $sales_internal_headers, $purchases_internal_headers;
    
    return [
        'headers_display' => ($type === 'ventas') ? $sales_display_headers : $purchases_display_headers,
        'headers_internal' => ($type === 'ventas') ? $sales_internal_headers : $purchases_internal_headers,
        'data' => $rows
    ];
}

?>

<?php
display_note(_("Los datos del CSV son procesados y mostrados en la tabla superior. Puede editar las celdas haciendo clic en ellas."), 0, 1);
div_end();
end_form();
?>

<?php
end_page();
?>