<?php
/** Validación de RUC compartida por los maestros (G5): 11 dígitos, prefijo válido y dígito verificador SUNAT. */
function ruc_valido(string $ruc): bool {
  if (!preg_match('/^\d{11}$/', $ruc) || !in_array(substr($ruc, 0, 2), ['10', '15', '16', '17', '20'], true)) return false;
  $suma = 0;
  foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) $suma += (int)$ruc[$i] * $peso;
  $digito = 11 - ($suma % 11);
  $digito = $digito === 10 ? 0 : ($digito === 11 ? 1 : $digito);
  return $digito === (int)$ruc[10];
}
