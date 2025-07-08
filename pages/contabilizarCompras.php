<?php
/**********************************************************************
*  contabilizar.php
*  Recibe la fila de la factura de compra desde import_siat_scripts.js,
*  genera el asiento contable y guarda el registro en 0_facturas_compra.
**********************************************************************/

header('Content-Type: text/html; charset=UTF-8');

$path_to_root = "..";

/* ──────────────────── FA / includes ──────────────────── */
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/admin/db/fiscalyears_db.inc");

/* ──────────────────── DEBUG opcional ──────────────────── */
error_log("POST recibido: " . json_encode($_POST));
error_log("=== MONTOS ORIGINALES ===");
error_log("DEBE1: $debe1_monto | DEBE2: $debe2_monto | HABER: $haber_monto");

function debug_to_console($data) {
    $output = $data;
    if (is_array($output))
        $output = implode(',', $output);

    echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}


/* ─────────── 1. Extraer parámetros (con valores por defecto) ────────── */
$fecha             = $_POST['fecha']             ?? '';
$memo              = $_POST['memo']              ?? 'Registro de Compra';
$tipo_fact         = $_POST['tipo_fact']         ?? '1';

$nit               = $_POST['nit']               ?? '';
$razon_social      = $_POST['razon_social']      ?? '';
$nro_fact          = $_POST['nro_fact']          ?? '';
$nro_pol           = $_POST['nro_pol']           ?? '';
$nro_auth          = $_POST['nro_auth']          ?? '';
$cod_control       = $_POST['cod_control']       ?? '';

$imp               = (float)($_POST['imp']               ?? 0);
$imp_ice           = (float)($_POST['imp_ice']           ?? 0);
$imp_exc           = (float)($_POST['imp_exc']           ?? 0);
$dbr               = (float)($_POST['dbr']               ?? 0);
$imp_cred_fiscal   = (float)($_POST['imp_cred_fiscal']   ?? 0);
$cred_fiscal       = (float)($_POST['cred_fiscal']       ?? 0);

$tcompra           = $_POST['tcompra']           ?? '1';

$debe1             = $_POST['debe1']             ?? '';
$debe1_monto       = (float)($_POST['debe1_monto']       ?? 0);
$debe2             = $_POST['debe2']             ?? '';
$debe2_monto       = (float)($_POST['debe2_monto']       ?? 0);
$haber             = $_POST['haber']             ?? '';
$haber_monto       = (float)($_POST['haber_monto']       ?? 0);

/* ─────────── 2. Validaciones básicas ────────── */
if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    die("Error: Formato de fecha inválido (use YYYY-MM-DD).");
}

/* ─────────── 3. Conversión de fecha al formato MM/DD/YYYY ────────── */
list($y, $m, $d) = explode('-', $fecha);

// Convertir la fecha a formato MM/DD/YYYY
$fecha_formateada = $m . '/' . $d . '/' . $y;

// Crear el memo con la fecha formateada
$monto_formateado = number_format($imp, 2, '.', ',');
$memo             = "Por el registro de la factura de " . $razon_social 
                  . " Nro. " . $nro_fact 
                  . " de fecha " . $fecha_formateada
                  . " por un importe de Bs " . $monto_formateado;

// Verificar el balance del asiento y ajustar diferencias mínimas
$total_debe = round($debe1_monto + $debe2_monto, 2);
$total_haber = round($haber_monto, 2);
$diferencia = round($total_debe - $total_haber, 2);

// Si hay una diferencia de centavos (±0.01), ajustar el debe1
if (abs($diferencia) == 0.01) {
    error_log("Ajustando diferencia de centavos: $diferencia");
    $debe1_monto = round($debe1_monto - $diferencia, 2);
    $total_debe = round($debe1_monto + $debe2_monto, 2);
    $diferencia = 0;
}

// Formatear los montos para mostrar siempre 2 decimales
$total_debe_formateado = number_format($total_debe, 2, '.', '');
$total_haber_formateado = number_format($total_haber, 2, '.', '');

// Verificar que ahora cuadre
if ($diferencia != 0) {
    die("Error: El asiento no cuadra. Debe = " . $total_debe_formateado . 
        " | Haber = " . $total_haber_formateado);
}

// Log de montos finales después del ajuste
error_log("=== MONTOS FINALES (después de ajuste si aplica) ===");
error_log("DEBE1: " . number_format($debe1_monto, 2, '.', '') . 
          " | DEBE2: " . number_format($debe2_monto, 2, '.', '') . 
          " | HABER: " . number_format($haber_monto, 2, '.', ''));
error_log("TOTAL DEBE: $total_debe_formateado | TOTAL HABER: $total_haber_formateado");

/* ─────────── 4. Conversión de fecha para FA ────────── */
$fecha_usuario    = __date($y, $m, $d);   // dd/mm/yy según prefs de usuario
$fecha_sql        = date2sql($fecha_usuario);

/* Comprobar año fiscal activo */
$fy = get_current_fiscalyear();
if (!$fy || $fecha_sql < $fy['begin'] || $fecha_sql > $fy['end']) {
    die("Error: La fecha $fecha_sql no está dentro del año fiscal activo.");
}

/* ─────────── 5. Comenzar transacción ────────── */
begin_transaction();

try {
    /* 5.1 Obtener número para Journal Entry */
    $trans_type = ST_JOURNAL;          // 0
    $trans_no   = get_next_trans_no($trans_type);

    /* 5.2 Asientos GL */
    add_gl_trans($trans_type, $trans_no, $fecha_usuario, $debe1, 0, 0, $memo,  $debe1_monto);        // Debe principal
    add_gl_trans($trans_type, $trans_no, $fecha_usuario, $debe2, 0, 0, $memo,  $debe2_monto);        // Debe (IVA)
    add_gl_trans($trans_type, $trans_no, $fecha_usuario, $haber, 0, 0, $memo, -$haber_monto);        // Haber

    /* 5.3 Línea resumen en tabla 0_gl_trans */
    add_journal($trans_type, $trans_no, $haber_monto, $fecha_usuario, get_company_currency(), '1');

    /* 5.4 Ref, comentarios y audit trail */
    global $Refs;
    $Refs->save($trans_type, $trans_no, '1');
    add_comments($trans_type, $trans_no, $fecha_usuario, $memo);
    add_audit_trail($trans_type, $trans_no, $fecha_usuario);

    /* ───── 5.5 Insertar en 0_facturas_compra ───── */
    /* Obtener siguiente compra_id */
    $row_max   = db_fetch(db_query("SELECT COALESCE(MAX(compra_id),0)+1 AS next_id FROM 0_facturas_compra"));
    $compra_id = $row_max['next_id'];

    $sql = "INSERT INTO 0_facturas_compra
           (compra_id, tipo_fact, nit_prov, razon_social, nro_fact,
            nro_pol, nro_auth, fecha, importe, imp_ice,
            imp_exc, dbr, imp_cred_fiscal, cred_fiscal, cod_control,
            tcompra,
            debe1, debe1_dimension1, debe1_dimension2,
            debe2, debe2_dimension1, debe2_dimension2,
            haber, haber_dimension1, haber_dimension2,
            memo, nro_trans)
     VALUES ($compra_id, '$tipo_fact', '$nit', '$razon_social', '$nro_fact',
             '$nro_pol', '$nro_auth', '$fecha_usuario', '$imp', '$imp_ice',
             '$imp_exc', '$dbr', '$imp_cred_fiscal', '$cred_fiscal', '$cod_control',
             '$tcompra',
             '$debe1', '0', '0',
             '$debe2', '0', '0',
             '$haber', '0', '0',
             '$memo', $trans_no)";

    db_query($sql, "No se pudo insertar la factura de compra (SQL).");

    /* ─────────── 6. Commit & Done ────────── */
    commit_transaction();
    echo $trans_no;   // ← el JS usará esto para abrir el asiento

} catch (Exception $e) {
    cancel_transaction();
    error_log("Error en contabilizar.php: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
?>