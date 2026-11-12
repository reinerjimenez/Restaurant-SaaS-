<?php

const CONTRASENA_MIN_CARACTERES = 12;
const CONTRASENA_MAX_BYTES = 72;

function error_contrasena(mixed $contrasena): ?string {
    if (!is_string($contrasena) || !mb_check_encoding($contrasena, 'UTF-8')
        || str_contains($contrasena, "\0") || trim($contrasena) === ''
        || mb_strlen($contrasena, 'UTF-8') < CONTRASENA_MIN_CARACTERES) {
        return 'La contraseña debe tener al menos 12 caracteres.';
    }
    if (strlen($contrasena) > CONTRASENA_MAX_BYTES) {
        return 'La contraseña no puede exceder 72 bytes con bcrypt.';
    }
    return null;
}
