<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Sistema de Bodega';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial, Helvetica, sans-serif;
            background:#f4f6f9;
            color:#1f2937;
        }
        a{
            text-decoration:none;
            color:inherit;
        }
        .topbar{
            background:#111827;
            color:#fff;
            padding:14px 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
        }
        .topbar__brand{
            font-size:20px;
            font-weight:700;
        }
        .topbar__user{
            font-size:14px;
            opacity:.95;
        }
        .layout{
            display:flex;
            min-height:calc(100vh - 56px);
        }
        .sidebar{
            width:260px;
            background:#1f2937;
            color:#fff;
            padding:20px 0;
        }
        .sidebar__title{
            padding:0 20px 14px;
            font-size:13px;
            text-transform:uppercase;
            letter-spacing:.08em;
            opacity:.7;
        }
        .menu{
            list-style:none;
            margin:0;
            padding:0;
        }
        .menu li{
            margin:0;
        }
        .menu a{
            display:block;
            padding:12px 20px;
            color:#e5e7eb;
            border-left:4px solid transparent;
        }
        .menu a:hover{
            background:#374151;
            border-left-color:#60a5fa;
        }
        .content{
            flex:1;
            padding:24px;
        }
        .card{
            background:#fff;
            border-radius:14px;
            padding:20px;
            box-shadow:0 8px 24px rgba(0,0,0,.06);
            margin-bottom:20px;
        }
        .page-title{
            margin:0 0 18px;
            font-size:28px;
        }
        .flash{
            padding:14px 16px;
            border-radius:10px;
            margin-bottom:18px;
            font-size:14px;
        }
        .flash--success{
            background:#dcfce7;
            color:#166534;
        }
        .flash--error{
            background:#fee2e2;
            color:#991b1b;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:18px;
        }
        .tile{
            background:#fff;
            border-radius:14px;
            padding:22px;
            box-shadow:0 8px 24px rgba(0,0,0,.06);
            transition:.2s ease;
        }
        .tile:hover{
            transform:translateY(-2px);
        }
        .tile__title{
            font-size:18px;
            font-weight:700;
            margin-bottom:8px;
        }
        .tile__text{
            font-size:14px;
            color:#6b7280;
            line-height:1.5;
        }
        .btn{
            display:inline-block;
            padding:10px 14px;
            border-radius:10px;
            background:#2563eb;
            color:#fff;
            font-size:14px;
            border:none;
            cursor:pointer;
        }
        .btn--secondary{
            background:#6b7280;
        }
        @media (max-width: 900px){
            .layout{
                display:block;
            }
            .sidebar{
                width:100%;
            }
            .content{
                padding:16px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar__brand">Sistema de Bodega</div>
        <div class="topbar__user">
            <?php if ($user): ?>
                Usuario: <strong><?php echo h($user['nombre']); ?></strong>
                (<?php echo h($user['rol']); ?>)
                &nbsp; | &nbsp;
                <a href="/Bodega/logout.php" style="color:#93c5fd;">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="layout">
        <?php if ($user): ?>
        <aside class="sidebar">
            <div class="sidebar__title">Menú principal</div>
            <ul class="menu">
                <li><a href="/Bodega/index.php">Inicio</a></li>
                <li><a href="/Bodega/bodegas/index.php">Bodegas</a></li>
                <li><a href="/Bodega/proveedores/index.php">Proveedores</a></li>
                <li><a href="/Bodega/productos/index.php">Productos</a></li>
                <li><a href="/Bodega/ordenes_compra/index.php">Órdenes de compra</a></li>
                <li><a href="/Bodega/facturas/index.php">Facturas</a></li>
                <li><a href="/Bodega/stock/index.php">Stock</a></li>
                <li><a href="/Bodega/movimientos/index.php">Movimientos</a></li>
                <li><a href="/Bodega/usuarios/index.php">Usuarios</a></li>
            </ul>
        </aside>
        <?php endif; ?>

        <main class="content">
            <?php if ($flash): ?>
                <div class="flash flash--<?php echo h($flash['type']); ?>">
                    <?php echo h($flash['message']); ?>
                </div>
            <?php endif; ?>