<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

$pageTitle = 'Inicio';
require_once __DIR__ . '/inc/header.php';
?>

<h1 class="page-title">Panel principal</h1>

<div class="card">
    <p style="margin:0; line-height:1.6; color:#4b5563;">
        Bienvenido al sistema de bodega. Desde aquí podrás administrar bodegas virtuales,
        proveedores, productos, órdenes de compra, facturas, stock y movimientos.
    </p>
</div>

<div class="grid">
    <a class="tile" href="/Bodega/bodegas/index.php">
        <div class="tile__title">Bodegas</div>
        <div class="tile__text">Crear y administrar las bodegas virtuales del sistema.</div>
    </a>

    <a class="tile" href="/Bodega/proveedores/index.php">
        <div class="tile__title">Proveedores</div>
        <div class="tile__text">Registrar proveedores asociados a órdenes de compra y facturas.</div>
    </a>

    <a class="tile" href="/Bodega/productos/index.php">
        <div class="tile__title">Productos</div>
        <div class="tile__text">Mantener catálogo de productos, tipo y unidad de medida.</div>
    </a>

    <a class="tile" href="/Bodega/ordenes_compra/index.php">
        <div class="tile__title">Órdenes de compra</div>
        <div class="tile__text">Registrar órdenes de compra y su detalle.</div>
    </a>

    <a class="tile" href="/Bodega/facturas/index.php">
        <div class="tile__title">Facturas</div>
        <div class="tile__text">Ingresar facturas y asociarlas a una orden de compra.</div>
    </a>

    <a class="tile" href="/Bodega/stock/index.php">
        <div class="tile__title">Stock</div>
        <div class="tile__text">Consultar existencias por producto y por bodega.</div>
    </a>

    <a class="tile" href="/Bodega/movimientos/index.php">
        <div class="tile__title">Movimientos</div>
        <div class="tile__text">Ver entradas, salidas, ajustes y traslados de inventario.</div>
    </a>

    <a class="tile" href="/Bodega/usuarios/index.php">
        <div class="tile__title">Usuarios</div>
        <div class="tile__text">Administrar accesos y roles del sistema.</div>
    </a>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>