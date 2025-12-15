    </main>
    <footer class="border-top mt-5">
      <div class="container-fluid px-3 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
        <div>© <?= date('Y') ?> SmartDoc • Patient Portal</div>
        <div class="d-flex gap-3">
          <a class="text-muted text-decoration-none" href="#">Privacy</a>
          <a class="text-muted text-decoration-none" href="#">Terms</a>
          <a class="text-muted text-decoration-none" href="#">Help</a>
        </div>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Auto-dismiss flashes after a short delay
      window.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
        alerts.forEach(a => setTimeout(() => {
          if (a.classList.contains('show')) new bootstrap.Alert(a).close();
        }, 3500));
      });
    </script>
  </body>
</html>

