# Comprobaciones ejecutadas

`python docs/auditoria/pruebas/validar_contratos.py`: lectura estática del SQL; no conecta BD. Salida 1 esperada en el estado auditado por H-018. No es un parser SQL general ni una prueba de importación.

Se intentó `C:/xampp/php/php.exe -l` sobre los PHP, pero Windows bloqueó el proceso (WinError 4551). No se validó sintaxis PHP. No se ejecutaron pruebas HTTP, concurrencia ni aislamiento con datos. No se llamó a la API externa. No se crearon bases de prueba.
