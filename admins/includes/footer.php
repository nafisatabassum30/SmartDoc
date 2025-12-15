    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    // Optional chart logic: only runs on pages that include a <canvas id="distBar">
    (() => {
      const el = document.getElementById('distBar');
      if(!el) return;
      const data = {
        labels: ['Doctors','Specializations','Hospitals','Symptoms'],
        datasets: [{
          label: 'Counts',
          data: [
            <?= (int)$con->query("SELECT COUNT(*) c FROM `doctor`")->fetch_assoc()['c'] ?? 0 ?>,
            <?= (int)$con->query("SELECT COUNT(*) c FROM `specialization`")->fetch_assoc()['c'] ?? 0 ?>,
            <?= (int)$con->query("SELECT COUNT(*) c FROM `hospital`")->fetch_assoc()['c'] ?? 0 ?>,
            <?= (int)$con->query("SELECT COUNT(*) c FROM `symptom`")->fetch_assoc()['c'] ?? 0 ?>
          ]
        }]
      };
      new Chart(el, {
        type: 'bar',
        data,
        options: {
          responsive:true,
          plugins:{ legend:{ display:false }},
          scales:{ y:{ beginAtZero:true, grid:{ color:'#eef2f7' }}, x:{ grid:{ display:false } } }
        }
      });
    })();
    </script>
    <script>
      // Auto-dismiss flash alerts
      window.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(a => setTimeout(() => {
          if (a.classList.contains('show')) new bootstrap.Alert(a).close();
        }, 3500));
      });
    </script>
  </body>
</html>
