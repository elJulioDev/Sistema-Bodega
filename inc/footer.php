</main><!-- /page-content -->


  <!-- ═══════════════════════════════════════════════
       FOOTER
  ════════════════════════════════════════════════ -->
  <footer id="footer">

    <div class="footer-info">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-primary); opacity:.6;">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
      </svg>
      <span class="footer-brand">Bodega Virtual</span>
      <span class="footer-sep">·</span>
      <span>&copy; <?php echo date('Y'); ?> <?php echo isset($organizacion) ? htmlspecialchars($organizacion) : 'Sistema de Gestión'; ?></span>
      <span class="footer-sep">·</span>
      <span>Todos los derechos reservados</span>
    </div>

    <div class="footer-links">
      <a href="index.php" class="footer-link">Inicio</a>
      <a href="ayuda.php" class="footer-link">Ayuda</a>
      <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'): ?>
      <a href="configuracion.php" class="footer-link">Configuración</a>
      <?php endif; ?>
    </div>

    <div class="footer-version">v1.0.0</div>

  </footer>

</div><!-- /wrapper -->

<!-- jQuery + Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>