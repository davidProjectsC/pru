<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Auth.php';
require_login();
header('Content-Type: text/html; charset=utf-8');

$usr = $_SESSION['usuario'];

// --- inputs ---
$pag    = $_GET['pag']    ?? $_POST['pag']    ?? '';
$tip    = $_GET['tip']    ?? $_POST['tip']    ?? '';
$mes    = $_GET['mes']    ?? $_POST['mes']    ?? '';
$anio   = $_GET['anio']   ?? $_POST['anio']   ?? date('Y');
$fcini  = $_GET['fcini']  ?? $_POST['fcini']  ?? '';
$fcfin  = $_GET['fcfin']  ?? $_POST['fcfin']  ?? '';
$cliente= $_POST['cliente'] ?? ($_GET['cliente'] ?? '');
$campos = $_GET['campos'] ?? $_POST['campos'] ?? 'Categoria,Presentacion,Marca';

function whitelist_fields(string $campos): array {
    $allowed = ['Categoria','Presentacion','Marca','Distrito','Agente','CteNombre','Cliente','Presentacion','Marca'];
    $parts = array_filter(array_map('trim', explode(',', $campos)));
    $safe  = [];
    foreach ($parts as $p) {
        if (in_array($p, $allowed, true)) $safe[] = $p;
    }
    if (!$safe) $safe = ['Categoria','Presentacion','Marca'];
    return array_values(array_unique($safe));
}

function rangoFechas(string $mes, string $anio, string $fcini, string $fcfin): array {
    if ($mes === '' && $fcini === '' && $fcfin === '') {
        $start = new DateTime(date('Y-m-01')); // primer día del mes actual
        $end   = new DateTime();               // hoy
    } elseif ($mes === 'año') {
        $start = new DateTime("$anio-01-01");
        $end   = (clone $start)->modify('+1 year -1 day');
    } elseif ($mes !== '') {
        $start = new DateTime(sprintf('%04d-%02d-01', (int)$anio, (int)$mes));
        $end   = (clone $start)->modify('+1 month -1 day');
    } else {
        $start = new DateTime($fcini);
        $end   = new DateTime($fcfin);
    }
    return [$start, $end];
}

list($fini, $ffin) = rangoFechas($mes, $anio, $fcini, $fcfin);
$cols = whitelist_fields($campos);
$camposSafe = implode(',', $cols);

// --- vista navegación superior (roles hardcodeados, como en el ASP) ---
$accestot = ['MPOLANCO','ESTEBANR','DRUIZ','ABASTA'];
$menuAutoriz = in_array($usr, $accestot, true);

// --- Helpers ---
function fmt_num($n): string {
    $v = (float)($n ?? 0);
    $s = number_format($v, 2, '.', ',');
    return $v < 0 ? "<span style='color:#c00'>{$s}</span>" : $s;
}

// --- Render header ---
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Comercial Roche</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Verdana, Geneva, sans-serif; font-size: 12px; }
table { border-collapse: collapse; font-size: 12px; }
td, th { border: 1px solid #ccc; padding: 4px 6px; }
.top { width:100%; background:#003366; color:#fff; }
.top a { color:#fff; text-decoration:none; padding: 0 8px; }
.top a:hover { color:#ff0; }
.small{ font-size: 11px; }
</style>
</head>
<body>
<?php if ($menuAutoriz): ?>
<table class="top"><tr>
  <td><b>&nbsp;&nbsp;<a href="/public/pru.php">Reporte de ventas</a> |</b></td>
  <td><b>&nbsp;&nbsp;<a href="/public/pru.php?pag=2">Autorización de Gastos</a> |</b></td>
  <td></td>
</tr></table>
<?php endif; ?>

<?php
if ($pag === '' || $pag === null) {
    // ------------------------
    // REPORTE VENTAS (Normal/Dif)
    // ------------------------
    // Si el usuario escribe texto en cliente sin guión, mostrar catálogo de clientes coincidentes (búsqueda)
    if ($cliente !== '' && strpos($cliente, '-') === false) {
        $sqlCli = "SELECT TOP 100 cliente, estatus, agente, nombre FROM Cte WHERE (cliente LIKE ? OR nombre LIKE ? OR nombrecorto LIKE ?)";
        $like = '%' . $cliente . '%';
        $st = $pdo->prepare($sqlCli);
        $st->execute([$like, $like, $like]);
        $clientes = $st->fetchAll();
        if ($clientes) {
            echo '<table><tr><th>Clave</th><th>ST</th><th>AG</th><th>Nombre</th></tr>';
            foreach ($clientes as $c) {
                $clave = htmlspecialchars($c['cliente'], ENT_QUOTES, 'UTF-8');
                $stt   = htmlspecialchars((string)$c['estatus'], ENT_QUOTES, 'UTF-8');
                $agt   = htmlspecialchars((string)$c['agente'], ENT_QUOTES, 'UTF-8');
                $nom   = htmlspecialchars((string)$c['nombre'], ENT_QUOTES, 'UTF-8');
                echo "<tr><td style='background:#003366'><a style='color:#fff' href='/public/pru.php?cliente={$clave}'>{$clave}</a></td><td>{$stt}</td><td>{$agt}</td><td>{$nom}</td></tr>";
            }
            echo '</table>';
        }
    } else {
        // Consulta principal (equivalente a la del ASP para tip="")
        $sql = "
SELECT  $camposSafe, SUM(Ant) AS [Año Ant], SUM(venta) AS Venta, SUM(Pres) AS Presupuesto
FROM (
    SELECT $camposSafe, [Año Ant] AS Ant, [Venta] AS venta, [Presupuesto] AS Pres
    FROM (
        SELECT Venta.Mov, Venta.MovID, MONTH(Venta.FechaEmision) AS Mes, VentaD.Articulo,
               RTRIM(art.articulo)+' '+Art.Descripcion1 AS descripcion1,
               VentaD.CantidadInventario*movtipo.factor1 AS ton, Art.Categoria, Venta.Agente,
               agente.Categoria AS DISTRITO, venta.almacen,
               ventad.cantidad*ventad.precio*venta.tipocambio*movtipo.factor1 AS importe,
               ventad.cantidad*ventad.costo*venta.tipocambio*movtipo.factor1  AS Costo,
               Venta.Cliente, cte.nombre AS CteNombre, cte.grupo,
               Art.Descripcion2 AS Marca, Art.Linea, Art.Presentacion,
               venta.fechaemision, YEAR(venta.fechaemision) AS anio, art.nombrecorto,
               ventad.descuentoimporte*movtipo.factor1 AS descuentos, ventad.descuentolinea,
               venta.empresa, 'Venta' AS Tipo, ventad.fecharequerida
        FROM COSYSA.dbo.Art Art
        JOIN COSYSA.dbo.VentaD VentaD ON VentaD.Articulo = Art.Articulo
        JOIN COSYSA.dbo.Venta Venta   ON Venta.ID = VentaD.ID
        JOIN COSYSA.dbo.movtipo movtipo ON Venta.Mov = movtipo.Mov AND movtipo.modulo='VTAS'
        JOIN COSYSA.dbo.agente Agente   ON venta.agente=agente.agente
        JOIN COSYSA.dbo.cte cte         ON Venta.Cliente = cte.Cliente
        WHERE venta.cliente NOT IN ('0001-1','0001-3')
          AND ((movtipo.clave='VTAS.F' AND Venta.Estatus='CONCLUIDO' AND Venta.FechaEmision BETWEEN ? AND ? AND Art.Familia='SAL')
            OR (movtipo.clave='VTAS.D' AND Venta.Estatus='CONCLUIDO' AND Venta.FechaEmision BETWEEN ? AND ? AND Art.Familia='SAL'))
          " . ($cliente !== '' ? " AND Venta.Cliente = ? " : "" ) . "
        UNION ALL
        SELECT Venta.Mov, Venta.MovID, MONTH(Venta.FechaEmision), VentaD.Articulo,
               RTRIM(art.articulo)+' '+Art.Descripcion1,
               VentaD.CantidadInventario*movtipo.factor1, Art.Categoria, Venta.Agente,
               agente.Categoria, venta.almacen,
               ventad.cantidad*ventad.precio*venta.tipocambio*movtipo.factor1,
               ventad.cantidad*ventad.costo*venta.tipocambio*movtipo.factor1,
               Venta.Cliente, cte.nombre, cte.grupo, Art.Descripcion2, Art.Linea, Art.Presentacion,
               venta.fechaemision, YEAR(venta.fechaemision), art.nombrecorto,
               ventad.descuentoimporte*movtipo.factor1, ventad.descuentolinea,
               venta.empresa, 'Año Ant' AS Tipo, ventad.fecharequerida
        FROM COSYSA.dbo.Art Art
        JOIN COSYSA.dbo.VentaD VentaD ON VentaD.Articulo = Art.Articulo
        JOIN COSYSA.dbo.Venta Venta   ON Venta.ID = VentaD.ID
        JOIN COSYSA.dbo.movtipo movtipo ON Venta.Mov = movtipo.Mov AND movtipo.modulo='VTAS'
        JOIN COSYSA.dbo.agente Agente   ON venta.agente=agente.agente
        JOIN COSYSA.dbo.cte cte         ON Venta.Cliente = cte.Cliente
        WHERE venta.cliente NOT IN ('0001-1','0001-3')
          AND ((movtipo.clave='VTAS.F' AND Venta.Estatus='CONCLUIDO' AND Venta.FechaEmision BETWEEN DATEADD(year,-1,?) AND DATEADD(year,-1,?) AND Art.Familia='SAL')
            OR (movtipo.clave='VTAS.D' AND Venta.Estatus='CONCLUIDO' AND Venta.FechaEmision BETWEEN DATEADD(year,-1,?) AND DATEADD(year,-1,?) AND Art.Familia='SAL'))
          " . ($cliente !== '' ? " AND Venta.Cliente = ? " : "" ) . "
        UNION ALL
        SELECT Venta.Mov, Venta.MovID, MONTH(Ventad.Fecharequerida), VentaD.Articulo,
               RTRIM(art.articulo)+' '+Art.Descripcion1,
               VentaD.CantidadInventario*movtipo.factor1, Art.Categoria, Venta.Agente,
               agente.Categoria, venta.almacen,
               ventad.cantidad*ventad.precio*venta.tipocambio*movtipo.factor1,
               ventad.cantidad*ventad.costo*venta.tipocambio*movtipo.factor1,
               Venta.Cliente, cte.nombre, cte.grupo, Art.Descripcion2, Art.Linea, Art.Presentacion,
               venta.fechaemision, YEAR(venta.fechaemision), art.nombrecorto,
               ventad.descuentoimporte*movtipo.factor1, ventad.descuentolinea,
               venta.empresa, 'Presupuesto' AS Tipo, ventad.fecharequerida
        FROM COSYSA.dbo.Art Art
        JOIN COSYSA.dbo.VentaD VentaD ON VentaD.Articulo = Art.Articulo
        JOIN COSYSA.dbo.Venta Venta   ON Venta.ID = VentaD.ID
        JOIN COSYSA.dbo.movtipo movtipo ON Venta.Mov = movtipo.Mov AND movtipo.modulo='VTAS'
        JOIN COSYSA.dbo.agente Agente   ON venta.agente=agente.agente
        JOIN COSYSA.dbo.cte cte         ON Venta.Cliente = cte.Cliente
        WHERE (Venta.Mov='Presupuesto' AND Venta.Estatus='CONCLUIDO'
               AND Ventad.Fecharequerida BETWEEN ? AND ? AND Art.Familia='SAL')
          " . ($cliente !== '' ? " AND Venta.Cliente = ? " : "" ) . "
    ) PVT
    PIVOT ( SUM(ton) FOR [tipo] IN ([Año Ant],[Venta],[Presupuesto]) ) AS pv
) AS t
GROUP BY $camposSafe WITH ROLLUP
ORDER BY $camposSafe;
        ";

        $params = [
            $fini->format('Y-m-d'), $ffin->format('Y-m-d'),
            $fini->format('Y-m-d'), $ffin->format('Y-m-d'),
            $fini->format('Y-m-d'), $ffin->format('Y-m-d'),
            $fini->format('Y-m-d'), $ffin->format('Y-m-d'),
            $fini->format('Y-m-d'), $ffin->format('Y-m-d'),
        ];
        if ($cliente !== '') { $params[] = $cliente; $params[] = $cliente; $params[] = $cliente; }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        // Render tabla
        echo '<h3>Reporte de ventas</h3>';
        echo '<div class="small">Rango: ' . htmlspecialchars($fini->format('Y-m-d')) . ' — ' . htmlspecialchars($ffin->format('Y-m-d')) . '</div>';
        echo '<table><tr>';
        foreach ($cols as $c) echo '<th>'.htmlspecialchars($c).'</th>';
        echo '<th>Año Ant.</th><th>Venta</th><th>Presup.</th></tr>';
        $Tant=0.0;$TVenta=0.0;$TPres=0.0;
        foreach ($rows as $r) {
            $Tant   += (float)($r['Año Ant'] ?? 0);
            $TVenta += (float)($r['Venta'] ?? 0);
            $TPres  += (float)($r['Presupuesto'] ?? 0);
            echo '<tr>';
            foreach ($cols as $c) echo '<td>'.htmlspecialchars((string)($r[$c] ?? '')).'</td>';
            echo '<td style="text-align:right">'.fmt_num($r['Año Ant'] ?? 0).'</td>';
            echo '<td style="text-align:right">'.fmt_num($r['Venta'] ?? 0).'</td>';
            echo '<td style="text-align:right">'.fmt_num($r['Presupuesto'] ?? 0).'</td>';
            echo '</tr>';
        }
        echo '<tr style="background:#C4D7FD"><td colspan="'.count($cols).'"><b>TOTAL</b></td>';
        echo '<td style="text-align:right"><b>'.fmt_num($Tant).'</b></td>';
        echo '<td style="text-align:right"><b>'.fmt_num($TVenta).'</b></td>';
        echo '<td style="text-align:right"><b>'.fmt_num($TPres).'</b></td></tr>';
        echo '</table>';
    }
} elseif ($pag === '2') {
    // ------------------------
    // AUTORIZACIÓN DE GASTOS
    // ------------------------
    $empresa = $_GET['empresa'] ?? $_POST['empresa'] ?? 'COSYSA,ROCHE'; // default como en ASP
    $aemp = array_map('trim', explode(',', $empresa));
    $dbName = $aemp[0] ?: 'COSYSA';

    // Conexión secundaria (a primera empresa de la lista)
    $dsn2 = "sqlsrv:Server=INTELISIS;Database={$dbName}";
    try {
        $pdo2 = new PDO($dsn2, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
        ]);
    } catch (Throwable $e) {
        echo '<div style="color:#b00">Error conexión secundaria: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</div>';
        $pdo2 = $pdo; // fallback
    }

    // Autorizar seleccionados
    if (!empty($_POST['it'])) {
        $ids = $_POST['it'];
        if (!is_array($ids)) $ids = [$ids];
        // Construir placeholders para IN
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sqlUp = "UPDATE Dinero SET SituacionUsuario = ?, Situacion = 'AUTORIZADO', SituacionFecha = GETDATE() WHERE Id IN ($place)";
        $params = array_merge([$usr], array_map('intval', $ids));
        $st = $pdo2->prepare($sqlUp);
        $st->execute($params);
    }

    $sqlSel = "SELECT Id, MovID, FechaEmision, Observaciones, Situacion, Importe, SituacionUsuario
               FROM Dinero
               WHERE mov = 'Solicitud Cheque' AND Estatus = 'Pendiente' AND ejercicio > 2010
               ORDER BY Id DESC";
    $rs = $pdo2->query($sqlSel)->fetchAll();

    echo '<h3>Autorización de Gastos</h3>';
    echo '<form method="post">';
    echo '<select name="empresa" onchange="this.form.submit()">';
    echo '<option value="CODESY,COSYS"'.($empresa==='CODESY,COSYS'?' selected':'').'>CODESY</option>';
    echo '<option value="COSYSA,ROCHE"'.($empresa==='COSYSA,ROCHE'?' selected':'').'>ROCHE</option>';
    echo '</select> ';
    echo '<button type="submit">Autorizar</button>';

    if ($rs) {
        echo '<table><tr style="background:#999"><th><input type="checkbox" onclick="for(const c of document.querySelectorAll(\'.chk\')) c.checked=this.checked"></th><th>MovId</th><th>Fecha Emisión</th><th>Situación</th><th>Observaciones</th><th>Importe</th></tr>';
        $alt=false;
        foreach ($rs as $row) {
            $alt = !$alt;
            $bg = $alt ? ' style="background:#F0F0F0"' : '';
            $canAuth = strtoupper((string)$row['Situacion']) !== 'AUTORIZADO';
            echo "<tr{$bg}>";
            echo '<td>';
            if ($canAuth) {
                echo '<input class="chk" type="checkbox" name="it[]" value="'.(int)$row['Id'].'">';
            }
            echo '</td>';
            echo '<td>&nbsp;&nbsp;'.htmlspecialchars((string)$row['MovID']).'&nbsp;&nbsp;</td>';
            echo '<td>&nbsp;&nbsp;'.htmlspecialchars((string)$row['FechaEmision']).'&nbsp;&nbsp;</td>';
            $color = $canAuth ? '#BB0000' : '#00AE00';
            echo '<td><b><span style="color:'.$color.'">&nbsp;&nbsp;'.htmlspecialchars((string)$row['Situacion']).' ('.htmlspecialchars((string)$row['SituacionUsuario']).')</span></b></td>';
            echo '<td>&nbsp;&nbsp;'.htmlspecialchars((string)$row['Observaciones']).'&nbsp;&nbsp;</td>';
            echo '<td style="text-align:right">&nbsp;&nbsp;'.fmt_num($row['Importe']).'&nbsp;&nbsp;</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No hay registros pendientes.</p>';
    }
    echo '</form>';
} else {
    echo '<p>Sección no encontrada.</p>';
}
?>
<p style="margin-top:24px"><a href="/public/salir.php">Salir</a></p>
</body>
</html>
