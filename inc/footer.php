</main> </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Modal de confirmación de acciones -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmModalTitle" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content app-modal">
            <div class="app-modal-body">
                <div class="app-modal-icon app-modal-icon-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h5 class="app-modal-title" id="confirmModalTitle">Confirmar acción</h5>
                <p class="app-modal-msg" id="confirmModalMsg">¿Continuar?</p>
            </div>
            <div class="app-modal-footer app-modal-footer-split">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmModalOk">
                    <i class="bi bi-check2 me-1"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de aviso -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true" aria-labelledby="alertModalTitle" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content app-modal">
            <div class="app-modal-body">
                <div class="app-modal-icon app-modal-icon-info">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <h5 class="app-modal-title" id="alertModalTitle">Aviso</h5>
                <p class="app-modal-msg" id="alertModalMsg"></p>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">
                    <i class="bi bi-check2 me-1"></i> Entendido
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var modalEl  = document.getElementById('confirmModal');
    var okBtn    = document.getElementById('confirmModalOk');
    var cTitle   = document.getElementById('confirmModalTitle');
    var cMsg     = document.getElementById('confirmModalMsg');
    var alertEl  = document.getElementById('alertModal');
    var pending  = null;
    var confirmModal = null;
    var alertModal   = null;

    function showConfirm(msg, title, onOk) {
        cMsg.textContent = msg;
        if (title) cTitle.textContent = title;
        pending = onOk;
        confirmModal.show();
    }

    /* ---------- Confirmación ---------- */
    if (modalEl && okBtn && cTitle && cMsg) {
        confirmModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });

        okBtn.addEventListener('click', function(){
            confirmModal.hide();
            var cb = pending;
            pending = null;
            if (typeof cb === 'function') cb();
        });

        // Si se cancela (Cerrar / backdrop / Escape), descartar la acción pendiente
        modalEl.addEventListener('hidden.bs.modal', function(){
            pending = null;
        });

        // Cualquier elemento con data-confirm (form, botón submit o enlace) abre el modal
        document.addEventListener('click', function(e){
            var t = e.target;
            if (!t || !t.closest) return;
            var el = t.closest('[data-confirm]');
            if (!el || el === modalEl || modalEl.contains(el)) return;
            var msg = el.getAttribute('data-confirm');
            if (!msg) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            showConfirm(msg, null, function(){
                if (el.tagName === 'FORM') {
                    el.dataset.confirmQueued = '1';
                    if (el.requestSubmit) { el.requestSubmit(); } else { el.submit(); }
                } else if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {
                    var f = el.form;
                    if (f) {
                        el.dataset.confirmQueued = '1';
                        if (f.requestSubmit) { f.requestSubmit(el); } else { f.submit(); }
                    }
                } else if (el.tagName === 'A') {
                    window.location.href = el.href;
                }
            });
        }, true);

        // Permite el envío real tras confirmar (formulario marcado con confirmQueued)
        // y también intercepta el envío por teclado (Enter) en formularios con data-confirm
        document.addEventListener('submit', function(e){
            var f = e.target;
            if (!f || !f.dataset || f.tagName !== 'FORM') return;
            if (f.dataset.confirmQueued === '1') {
                delete f.dataset.confirmQueued;
                return;
            }
            var msg = f.getAttribute('data-confirm');
            if (!msg) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            showConfirm(msg, null, function(){
                f.dataset.confirmQueued = '1';
                if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }
            });
        }, true);
    }

    window.uiConfirm = function(msg, onOk, title) {
        if (confirmModal) {
            showConfirm(msg, title || 'Confirmar acción', onOk);
        } else {
            window.confirm(msg);
        }
    };

    /* ---------- Aviso (reemplaza alert()) ---------- */
    if (alertEl) {
        alertModal = new bootstrap.Modal(alertEl);
    }
    window.uiAlert = function(msg, title, type) {
        if (!alertModal) { window.alert(msg); return; }
        var key = type || 'info';
        var cfg = {
            'success': ['app-modal-icon-success', 'bi-check-circle-fill'],
            'danger':  ['app-modal-icon-danger',  'bi-x-circle-fill'],
            'warning': ['app-modal-icon-warning', 'bi-exclamation-triangle-fill'],
            'info':    ['app-modal-icon-info',    'bi-info-circle-fill']
        }[key] || ['app-modal-icon-info', 'bi-info-circle-fill'];
        var iconEl = alertEl.querySelector('.app-modal-icon');
        var iEl = iconEl.querySelector('i');
        iconEl.className = 'app-modal-icon ' + cfg[0];
        iEl.className = 'bi ' + cfg[1];
        var tEl = document.getElementById('alertModalTitle');
        var mEl = document.getElementById('alertModalMsg');
        tEl.textContent = title || (key === 'danger' ? 'Error' : (key === 'warning' ? 'Atención' : 'Aviso'));
        mEl.textContent = msg;
        alertModal.show();
    };
})();
</script>
</body>
</html>
