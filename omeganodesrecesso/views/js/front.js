/**
 * OmegaNodes — Diritto di Recesso (54-bis)
 * UX non bloccante: nessuna logica di validazione lato solo-client.
 * La validazione autoritativa resta sempre server-side.
 *
 * @author    OmegaNodes Company Ltd <https://omeganodes.ai>
 * @copyright 2026 OmegaNodes Company Ltd
 * @license   https://opensource.org/licenses/AFL-3.0
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Auto-caricamento righe quando si sceglie un ordine (comodita', non obbligo).
    var select = document.querySelector('.omega-order-select');
    if (select) {
      select.addEventListener('change', function () {
        if (select.value && select.form) {
          select.form.submit();
        }
      });
    }

    // Abilita il campo quantita' insieme alla checkbox (coerenza visiva).
    var rows = document.querySelectorAll('.omega-lines tr');
    rows.forEach(function (row) {
      var check = row.querySelector('input[type="checkbox"][name="line_select[]"]');
      var qty = row.querySelector('input.omega-qty');
      if (check && qty) {
        check.addEventListener('change', function () {
          qty.disabled = !check.checked;
        });
      }
    });

    // Evita il doppio invio della conferma (idempotenza gia' garantita lato server).
    var confirmForm = document.querySelector('.omega-confirm-form');
    if (confirmForm) {
      confirmForm.addEventListener('submit', function () {
        var btn = confirmForm.querySelector('button[type="submit"]');
        if (btn) {
          window.setTimeout(function () { btn.disabled = true; }, 0);
        }
      });
    }
  });
})();
