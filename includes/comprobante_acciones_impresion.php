<?php
/** Acciones del comprobante que la vista ya autorizó y cargó. */
$ticketTermico = function_exists('iconv') ? comprobante_escpos($comp, $itemsAgrupados, $pagos) : '';
// Base64 necesita cuatro caracteres por cada tres bytes. El cliente cuenta también el sufijo del intent.
$ticketBase64 = strlen($ticketTermico) <= 6144 ? base64_encode($ticketTermico) : '';
?>
<?php if ($ticketBase64 !== ''): ?>
<a class="btn btn-primario btn-sm ticket-rawbt" data-ticket="<?= htmlspecialchars($ticketBase64, ENT_QUOTES, 'UTF-8') ?>"
   aria-describedby="ticket-rawbt-estado" hidden>
    <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir en RawBT
</a>
<?php endif; ?>
<button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">
    <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir desde el navegador
</button>
<p class="ticket-rawbt-estado" id="ticket-rawbt-estado" role="status"></p>
<script defer src="<?= BASE_URL ?>/assets/js/comprobante_termico.js"></script>
