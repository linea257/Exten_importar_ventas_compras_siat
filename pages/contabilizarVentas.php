<?php
/**********************************************************************
*  contabilizarVentas.php - CORREGIDO
*  Recibe la fila de la factura de venta desde importar_ventas.js,
*  genera el asiento contable con 5 cuentas y guarda el registro en 0_facturas_venta.
**********************************************************************/

header('Content-Type: text/html; charset=UTF-8');

$path_to_root = "..";

/* ──────────────────── FA / includes ──────────────────── */
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/admin/db/fiscalyears_db.inc");

/* ──────────────────── DEBUG detallado ──────────────────── */
error_log("=== INICIO contabilizarVentas.php ===");
error_log("POST recibido: " . json_encode($_POST));

/* ─────────── 1. Extraer parámetros de ventas ────────── */
$fecha             = $_POST['fecha']             ?? '';
$memo              = $_POST['memo']              ?? 'Registro de Venta';
$tipo_fact         = $_POST['tipo_fact']         ?? '1';

// Datos del cliente
$nit_cliente       = $_POST['nit_cliente']       ?? '';
$razon_social      = $_POST['razon_social']      ?? '';
$nro_fact          = $_POST['nro_fact']          ?? '';
$nro_auth          = $_POST['nro_auth']          ?? '';
$cod_control       = $_POST['cod_control']       ?? '';

// Importes de venta
$importe_total     = (float)($_POST['importe_total']     ?? 0);
$imp_ice           = (float)($_POST['imp_ice']           ?? 0);
$imp_iehd          = (float)($_POST['imp_iehd']          ?? 0);
$imp_ipj           = (float)($_POST['imp_ipj']           ?? 0);
$tasas             = (float)($_POST['tasas']             ?? 0);
$otros_no_sujetos  = (float)($_POST['otros_no_sujetos'] ?? 0);
$exportaciones_exentas = (float)($_POST['exportaciones_exentas'] ?? 0);
$ventas_tasa_cero  = (float)($_POST['ventas_tasa_cero'] ?? 0);
$subtotal          = (float)($_POST['subtotal']         ?? 0);
$descuentos        = (float)($_POST['descuentos']       ?? 0);
$gift_card         = (float)($_POST['gift_card']        ?? 0);
$base_debito_fiscal = (float)($_POST['base_debito_fiscal'] ?? 0);
$debito_fiscal     = (float)($_POST['debito_fiscal']    ?? 0);

// Estados y clasificaciones
$estado            = $_POST['estado']            ?? 'ACTIVO';
$tipo_venta        = $_POST['tipo_venta']        ?? '1';
$con_derecho_cf    = $_POST['con_derecho_cf']    ?? 'SI';
$estado_consolidacion = $_POST['estado_consolidacion'] ?? 'NORMAL';

// Cuentas contables (5 cuentas para ventas)
$debe1             = $_POST['debe1']             ?? '';
$debe1_monto       = (float)($_POST['debe1_monto']       ?? 0);
$debe2             = $_POST['debe2']             ?? '';
$debe2_monto       = (float)($_POST['debe2_monto']       ?? 0);
$haber1            = $_POST['haber1']            ?? '';
$haber1_monto      = (float)($_POST['haber1_monto']      ?? 0);
$haber2            = $_POST['haber2']            ?? '';
$haber2_monto      = (float)($_POST['haber2_monto']      ?? 0);
$haber3            = $_POST['haber3']            ?? '';
$haber3_monto      = (float)($_POST['haber3_monto']      ?? 0);

/* ──────────── DEBUG de montos recibidos ──────────── */
error_log("=== MONTOS RECIBIDOS ===");
error_log("Importe Total: $importe_total");
error_log("Base Débito Fiscal: $base_debito_fiscal");
error_log("Débito Fiscal: $debito_fiscal");
error_log("---");
error_log("DEBE 1 ($debe1): $debe1_monto");
error_log("DEBE 2 ($debe2): $debe2_monto");
error_log("HABER 1 ($haber1): $haber1_monto");
error_log("HABER 2 ($haber2): $haber2_monto");
error_log("HABER 3 ($haber3): $haber3_monto");

// DEBUG adicional para verificar si los montos vienen como string
error_log("=== VERIFICACIÓN DE TIPOS ===");
error_log("haber1_monto tipo: " . gettype($haber1_monto) . " valor original: " . ($_POST['haber1_monto'] ?? 'NO DEFINIDO'));
error_log("haber2_monto tipo: " . gettype($haber2_monto) . " valor original: " . ($_POST['haber2_monto'] ?? 'NO DEFINIDO'));
error_log("haber3_monto tipo: " . gettype($haber3_monto) . " valor original: " . ($_POST['haber3_monto'] ?? 'NO DEFINIDO'));

/* ─────────── 2. Validaciones básicas ────────── */
if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    error_log("Error: Fecha inválida - $fecha");
    die("Error: Formato de fecha inválido (use YYYY-MM-DD).");
}

if ($importe_total <= 0) {
    error_log("Error: Importe total inválido - $importe_total");
    die("Error: El importe total de la venta debe ser mayor a 0.");
}

if (empty($nro_fact)) {
    error_log("Error: Número de factura vacío");
    die("Error: Número de factura requerido.");
}

if (empty($nro_auth)) {
    error_log("Error: Código de autorización vacío");
    die("Error: Código de autorización requerido.");
}

// Validar que las cuentas principales estén definidas
// En ventas, debe1 y haber1 son obligatorias, las demás son opcionales
if (empty($debe1) || empty($haber1)) {
    error_log("Error: Cuentas principales faltantes - D1:$debe1, H1:$haber1");
    die("Error: Las cuentas principales (DEBE1 y HABER1) son requeridas.");
}

// Si hay montos en las cuentas secundarias, verificar que las cuentas estén definidas
if (($debe2_monto > 0 && empty($debe2)) || 
    ($haber2_monto > 0 && empty($haber2)) || 
    ($haber3_monto > 0 && empty($haber3))) {
    error_log("Error: Cuentas secundarias faltantes con montos - D2:$debe2($debe2_monto), H2:$haber2($haber2_monto), H3:$haber3($haber3_monto)");
    die("Error: Si hay montos en cuentas secundarias, las cuentas deben estar definidas.");
}

// Validar que los montos principales no sean cero
// El debe1 y haber1 siempre deben tener valor, los demás pueden ser 0
if ($debe1_monto <= 0 || $haber1_monto <= 0) {
    error_log("Error: Montos principales inválidos - DEBE1:$debe1_monto, HABER1:$haber1_monto");
    die("Error: Los montos principales del asiento (DEBE1 y HABER1) deben ser mayores a 0.");
}

// Validar que los montos secundarios no sean negativos
if ($debe2_monto < 0 || $haber2_monto < 0 || $haber3_monto < 0) {
    error_log("Error: Montos negativos - D2:$debe2_monto, H2:$haber2_monto, H3:$haber3_monto");
    die("Error: Los montos secundarios no pueden ser negativos.");
}

// Verificar que el asiento cuadre con mayor tolerancia para redondeos
$total_debe = round($debe1_monto + $debe2_monto, 2);
$total_haber = round($haber1_monto + $haber2_monto + $haber3_monto, 2);
$diferencia = abs($total_debe - $total_haber);

error_log("=== VERIFICACIÓN DE CUADRE ===");
error_log("Total DEBE: $total_debe");
error_log("Total HABER: $total_haber");
error_log("Diferencia: $diferencia");

if ($diferencia > 0.02) { // Tolerancia de 2 centavos para redondeos
    error_log("Error: Asiento no cuadra - DEBE:$total_debe, HABER:$total_haber, Diff:$diferencia");
    die("Error: El asiento no cuadra. DEBE = $total_debe | HABER = $total_haber | Diferencia = $diferencia");
}

/* ─────────── 3. Conversión de fecha al formato FA ────────── */
list($y, $m, $d) = explode('-', $fecha);

// Convertir la fecha a formato MM/DD/YYYY para el memo
$fecha_formateada = $m . '/' . $d . '/' . $y;

// Crear el memo con la fecha formateada
$monto_formateado = number_format($importe_total, 2, '.', ',');
$memo = "Por el registro de la factura de venta a " . $razon_social 
      . " Nro. " . $nro_fact 
      . " de fecha " . $fecha_formateada
      . " por un importe de Bs " . $monto_formateado;

$fecha_usuario    = __date($y, $m, $d);   // dd/mm/yy según prefs de usuario
$fecha_sql        = date2sql($fecha_usuario);

error_log("Fecha convertida: $fecha -> $fecha_usuario -> $fecha_sql");

/* Comprobar año fiscal activo */
$fy = get_current_fiscalyear();
if (!$fy || $fecha_sql < $fy['begin'] || $fecha_sql > $fy['end']) {
    error_log("Error: Fecha fuera del año fiscal - $fecha_sql");
    die("Error: La fecha $fecha_sql no está dentro del año fiscal activo.");
}

/* ─────────── 4. Comenzar transacción ────────── */
error_log("Iniciando transacción...");
begin_transaction();

try {
    /* 4.1 Obtener número para Journal Entry */
    $trans_type = ST_JOURNAL;          // 0
    $trans_no   = get_next_trans_no($trans_type);
    
    error_log("Número de transacción asignado: $trans_no");

    /* 4.2 Asientos GL - 5 movimientos para ventas */
    error_log("=== CREANDO ASIENTOS GL ===");
    
    // DEBE 1: Cuenta principal (generalmente caja/bancos)
    error_log("GL 1: $debe1 = +$debe1_monto (DEBE)");
    add_gl_trans($trans_type, $trans_no, $fecha_usuario, $debe1, 0, 0, $memo, $debe1_monto);
    
    // DEBE 2: Gasto por impuesto a las transacciones
    if ($debe2_monto > 0) {
        error_log("GL 2: $debe2 = +$debe2_monto (DEBE)");
        add_gl_trans($trans_type, $trans_no, $fecha_usuario, $debe2, 0, 0, $memo, $debe2_monto);
    }
    
    // HABER 1: Ingresos por ventas (sin IVA)
    error_log("GL 3: $haber1 = -$haber1_monto (HABER)");
    add_gl_trans($trans_type, $trans_no, $fecha_usuario, $haber1, 0, 0, $memo, -$haber1_monto);
    
    // HABER 2: Débito fiscal (IVA por pagar)
    if ($haber2_monto > 0) {
        error_log("GL 4: $haber2 = -$haber2_monto (HABER)");
        add_gl_trans($trans_type, $trans_no, $fecha_usuario, $haber2, 0, 0, $memo, -$haber2_monto);
    }
    
    // HABER 3: Impuesto a las transacciones por pagar
    if ($haber3_monto > 0) {
        error_log("GL 5: $haber3 = -$haber3_monto (HABER)");
        add_gl_trans($trans_type, $trans_no, $fecha_usuario, $haber3, 0, 0, $memo, -$haber3_monto);
    }

    /* 4.3 Línea resumen en tabla 0_gl_trans */
    error_log("Creando journal entry con total: $total_debe");
    add_journal($trans_type, $trans_no, $total_debe, $fecha_usuario, get_company_currency(), '1');

    /* 4.4 Ref, comentarios y audit trail */
    global $Refs;
    $Refs->save($trans_type, $trans_no, '1');
    add_comments($trans_type, $trans_no, $fecha_usuario, $memo);
    add_audit_trail($trans_type, $trans_no, $fecha_usuario);

    /* ───── 4.5 Insertar en 0_facturas_venta ───── */
    $row_max   = db_fetch(db_query("SELECT COALESCE(MAX(venta_id),0)+1 AS next_id FROM 0_facturas_venta"));
    $venta_id = $row_max['next_id'];
    
    error_log("Insertando en 0_facturas_venta con venta_id: $venta_id");

    // SQL corregido según la estructura real de la tabla
    $sql = "INSERT INTO 0_facturas_venta
           (venta_id, fecha, nro_fact, nro_auth, estado,
            nit, razon_social, importe, imp_ice, imp_exc,
            tasa_cero, dbr, imp_deb_fiscal, deb_fiscal,
            cod_control, debe1, debe1_dimension1, debe1_dimension2,
            debe2, debe2_dimension1, debe2_dimension2,
            haber1, haber1_dimension1, haber1_dimension2,
            haber2, haber2_dimension1, haber2_dimension2,
            haber3, haber3_dimension1, haber3_dimension2,
            memo, nro_trans)
     VALUES ($venta_id, ".db_escape($fecha_usuario).", ".db_escape($nro_fact).", ".db_escape($nro_auth).", ".db_escape($estado).",
             ".db_escape($nit_cliente).", ".db_escape($razon_social).", $importe_total, $imp_ice, $exportaciones_exentas,
             $ventas_tasa_cero, $base_debito_fiscal, $debito_fiscal, $debito_fiscal,
             ".db_escape($cod_control).", ".db_escape($debe1).", 0, 0,
             ".db_escape($debe2).", 0, 0,
             ".db_escape($haber1).", 0, 0,
             ".db_escape($haber2).", 0, 0,
             ".db_escape($haber3).", 0, 0,
             ".db_escape($memo).", $trans_no)";

    error_log("SQL a ejecutar: $sql");
    db_query($sql, "No se pudo insertar la factura de venta (SQL).");

    /* ─────────── 5. Commit & Done ────────── */
    commit_transaction();
    
    // Log de éxito
    error_log("=== VENTA CONTABILIZADA EXITOSAMENTE ===");
    error_log("Trans: $trans_no | Factura: $nro_fact | Total: $importe_total");
    error_log("Cliente: $razon_social | NIT: $nit_cliente");
    error_log("Asiento: DEBE=$total_debe, HABER=$total_haber");
    
    echo $trans_no;  

} catch (Exception $e) {
    cancel_transaction();
    error_log("=== ERROR EN TRANSACCIÓN ===");
    error_log("Mensaje: " . $e->getMessage());
    error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo "Error: " . $e->getMessage();
}
?>