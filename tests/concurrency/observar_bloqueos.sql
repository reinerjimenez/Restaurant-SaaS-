-- Solo lectura. Abrir en una conexión operadora independiente dentro de la VM.
-- Requiere PROCESS para los diagnósticos InnoDB. No concederlo a ola1_app.
SELECT DATABASE() AS base, @@hostname AS equipo, CONNECTION_ID() AS observador,
       @@innodb_lock_wait_timeout AS espera_segundos, @@tx_isolation AS aislamiento;

SELECT w.requesting_trx_id AS transaccion_en_espera, pet.trx_mysql_thread_id AS hilo_en_espera,
       w.blocking_trx_id AS transaccion_bloqueante, bloq.trx_mysql_thread_id AS hilo_bloqueante,
       pet.trx_wait_started AS inicio_espera, l.lock_table AS tabla, l.lock_index AS indice,
       l.lock_type AS tipo, l.lock_mode AS modo
FROM information_schema.INNODB_LOCK_WAITS w
JOIN information_schema.INNODB_TRX pet ON pet.trx_id = w.requesting_trx_id
JOIN information_schema.INNODB_TRX bloq ON bloq.trx_id = w.blocking_trx_id
JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id
WHERE DATABASE() = 'restaurant_ola1_test'
  AND LEFT(l.lock_table, LENGTH(DATABASE()) + 3) = CONCAT('`', DATABASE(), '`.')
ORDER BY pet.trx_wait_started;

SELECT trx_mysql_thread_id AS hilo, trx_id AS transaccion, trx_state AS estado,
       trx_started AS inicio, trx_wait_started AS inicio_espera
FROM information_schema.INNODB_TRX
WHERE trx_state IN ('RUNNING', 'LOCK WAIT') ORDER BY trx_started;
