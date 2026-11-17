"""Pruebas simuladas de rechazo. Solo usan archivos temporales y funciones locales."""
import hashlib
import importlib.util
import json
import pathlib
import tempfile
import unittest

RAIZ = pathlib.Path(__file__).resolve().parents[2]


def modulo(nombre):
    ruta = RAIZ / 'scripts' / 'ensayo' / (nombre + '.py')
    spec = importlib.util.spec_from_file_location(nombre, ruta)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


class PaqueteSimulado(unittest.TestCase):
    def test_autorizacion(self):
        validar = modulo('validar_autorizacion').validar
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            self.assertTrue(pathlib.Path(temporal).resolve().is_relative_to(RAIZ.resolve()))
            carpeta = pathlib.Path(temporal)
            sql = carpeta / '004_ensayo.sql'
            sql.write_text('SELECT 1', encoding='utf-8')
            vm = carpeta / 'vm.json'
            vm.write_text(json.dumps({'vm_uuid': 'vm-123', 'hostname': 'vm-ola1'}), encoding='utf-8')
            hoja = carpeta / 'hoja.json'
            datos = dict(id='A1', operador='operador', vm_uuid='vm-123', hostname='vm-ola1',
                         puerto=3306, esquema='restaurant_ola1_test',
                         sql_sha256=hashlib.sha256(sql.read_bytes()).hexdigest(),
                         respaldo_sha256='a' * 64, fecha='2026-11-11', ventana='ensayo',
                         preflight='verificado', aprobacion_expresa='sí')
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertTrue(validar(hoja, sql, vm))
            for clave, incorrecto in [('hostname', 'otra-vm'), ('puerto', 3307),
                                      ('esquema', 'produccion'), ('sql_sha256', '0' * 64),
                                      ('aprobacion_expresa', '')]:
                alterado = datos | {clave: incorrecto}
                hoja.write_text(json.dumps(alterado), encoding='utf-8')
                self.assertFalse(validar(hoja, sql, vm), clave)
            sql_006 = carpeta / '006_ensayo.sql'
            sql_006.write_text('SELECT 1', encoding='utf-8')
            datos['sql_sha256'] = hashlib.sha256(sql_006.read_bytes()).hexdigest()
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertFalse(validar(hoja, sql_006, vm))

    def test_respaldo(self):
        validar = modulo('validar_respaldo').validar
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            self.assertTrue(pathlib.Path(temporal).resolve().is_relative_to(RAIZ.resolve()))
            carpeta = pathlib.Path(temporal)
            archivo = carpeta / 'ola1.sql'
            contenido = b'CREATE TABLE `turnos` (id INT) ENGINE=InnoDB;\nINSERT INTO `turnos` VALUES (1);\n'
            archivo.write_bytes(contenido)
            hoja = carpeta / 'corte.json'
            datos = dict(origen='restaurant_ola1_test', destino='restaurant_ola1_restore',
                         tamano=len(contenido), sha256=hashlib.sha256(contenido).hexdigest(),
                         tablas=['turnos'])
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertTrue(validar(archivo, hoja))
            for extra in (b'DROP DATABASE produccion;', b'USE produccion;',
                          b'INSERT INTO produccion.turnos VALUES (1);', b'CREATE DATABASE x;'):
                alterado = contenido + extra
                archivo.write_bytes(alterado)
                hoja.write_text(json.dumps(datos | {'tamano': len(alterado),
                    'sha256': hashlib.sha256(alterado).hexdigest()}), encoding='utf-8')
                self.assertFalse(validar(archivo, hoja))
            archivo.write_bytes(contenido + b' ')
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertFalse(validar(archivo, hoja))

    def test_respaldo_opciones_mariadb_y_comentarios(self):
        validar = modulo('validar_respaldo').validar
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            carpeta = pathlib.Path(temporal)
            contenido = (b"CREATE TABLE `turnos` (id INT, nota TEXT) ENGINE=InnoDB "
                         b"AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n"
                         b"INSERT INTO `turnos` VALUES (1, 'texto; -- literal # dato');\n")
            archivo = carpeta / 'ola1.sql'
            archivo.write_bytes(contenido)
            hoja = carpeta / 'corte.json'
            datos = dict(origen='restaurant_ola1_test', destino='restaurant_ola1_restore',
                         tamano=len(contenido), sha256=hashlib.sha256(contenido).hexdigest(),
                         tablas=['turnos'])
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertTrue(validar(archivo, hoja))
            directivas = (b'/*!40101 SET @saved_cs_client = @@character_set_client */;\n'
                          b'/*!40101 SET character_set_client = utf8 */;\n'
                          b'CREATE TABLE' + contenido.split(b'CREATE TABLE', 1)[1].split(b'INSERT INTO', 1)[0] +
                          b'/*!40101 SET character_set_client = @saved_cs_client */;\n' +
                          b'INSERT INTO' + contenido.split(b'INSERT INTO', 1)[1])
            archivo.write_bytes(directivas)
            hoja.write_text(json.dumps(datos | {'tamano': len(directivas),
                'sha256': hashlib.sha256(directivas).hexdigest()}), encoding='utf-8')
            self.assertTrue(validar(archivo, hoja))
            for comentario in (b'-- instruccion\n', b'/* instruccion */', b'# instruccion\n'):
                alterado = contenido + comentario
                archivo.write_bytes(alterado)
                hoja.write_text(json.dumps(datos | {'tamano': len(alterado),
                    'sha256': hashlib.sha256(alterado).hexdigest()}), encoding='utf-8')
                self.assertFalse(validar(archivo, hoja))
            no_autorizada = directivas.replace(b'/*!40101 SET character_set_client = utf8 */',
                                               b'/*!40101 SET character_set_client = latin1 */')
            archivo.write_bytes(no_autorizada)
            hoja.write_text(json.dumps(datos | {'tamano': len(no_autorizada),
                'sha256': hashlib.sha256(no_autorizada).hexdigest()}), encoding='utf-8')
            self.assertFalse(validar(archivo, hoja))

    def test_respaldo_literales_no_se_confunden_con_sql(self):
        validar = modulo('validar_respaldo').validar
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            carpeta = pathlib.Path(temporal)
            contenido = ("CREATE TABLE `clientes` (`id` INT NOT NULL, `correo` TEXT, `texto` TEXT, "
                         "`modificado` DATETIME DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(), "
                         "PRIMARY KEY (`id`), CONSTRAINT `fk_clientes_self` FOREIGN KEY (`id`) "
                         "REFERENCES `clientes` (`id`) ON DELETE CASCADE) "
                         "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n"
                         "INSERT INTO `clientes` VALUES (1, 'ana@example.invalid', "
                         "'DROP SELECT INSERT DELETE; O''Brien; barra \\\\; @ dato'), "
                         "(2, \"SELECT; correo@ejemplo.invalid\", NULL);\n").encode('utf-8')
            archivo = carpeta / 'ola1.sql'
            archivo.write_bytes(contenido)
            hoja = carpeta / 'corte.json'
            datos = dict(origen='restaurant_ola1_test', destino='restaurant_ola1_restore',
                         tamano=len(contenido), sha256=hashlib.sha256(contenido).hexdigest(),
                         tablas=['clientes'])
            hoja.write_text(json.dumps(datos), encoding='utf-8')
            self.assertTrue(validar(archivo, hoja))

    def test_manifiesto_inventario_hash_y_sql_se_rechazan(self):
        validar = modulo('validar_respaldo').validar
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            carpeta = pathlib.Path(temporal)
            contenido = (b'CREATE TABLE `clientes` (id INT) ENGINE=InnoDB;\n'
                         b"INSERT INTO `clientes` VALUES (-1);\n")
            archivo = carpeta / 'ola1.sql'
            archivo.write_bytes(contenido)
            hoja = carpeta / 'corte.json'
            base = dict(origen='restaurant_ola1_test', destino='restaurant_ola1_restore',
                        tamano=len(contenido), sha256=hashlib.sha256(contenido).hexdigest(),
                        tablas=['clientes'])
            hoja.write_text(json.dumps(base), encoding='utf-8')
            self.assertTrue(validar(archivo, hoja))
            invalidos = [
                base | {'tablas': ['clientes', 'clientes']},
                base | {'tablas': ['otra_tabla']},
                base | {'tamano': len(contenido) + 1},
                base | {'sha256': '0' * 64},
                base | {'destino': 'produccion'},
                base | {'comentario': 'campo no firmado'},
            ]
            for manifiesto in invalidos:
                hoja.write_text(json.dumps(manifiesto), encoding='utf-8')
                self.assertFalse(validar(archivo, hoja), manifiesto)
            for sql in (b'DROP TABLE `clientes`;', b'CREATE DATABASE otra;', b'USE otra;',
                        b'INSERT INTO otra.clientes VALUES (1);', b'/* comentario */',
                        b'CREATE TABLE `clientes` (id INT) ENGINE=InnoDB; SELECT 1;',
                        b'CREATE TABLE `clientes` (id INT, FOREIGN KEY (id) REFERENCES otra.clientes(id)) ENGINE=InnoDB;',
                        b'/*!40101 SET @otra_variable=1 */;'):
                archivo.write_bytes(sql)
                manifiesto = base | {'tamano': len(sql), 'sha256': hashlib.sha256(sql).hexdigest()}
                hoja.write_text(json.dumps(manifiesto), encoding='utf-8')
                self.assertFalse(validar(archivo, hoja), sql)
            incompleto = b'CREATE TABLE `clientes` (id INT) ENGINE=InnoDB'
            archivo.write_bytes(incompleto)
            hoja.write_text(json.dumps(base | {'tamano': len(incompleto),
                'sha256': hashlib.sha256(incompleto).hexdigest()}), encoding='utf-8')
            self.assertFalse(validar(archivo, hoja), 'SQL sin terminador')

    def test_reserva_y_publicacion_sin_reemplazo(self):
        reserva = modulo('reservar_respaldo')
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            raiz = pathlib.Path(temporal).resolve()
            directorio = raiz / 'corte_001'
            reserva.reservar(directorio, raiz)
            (directorio / 'ola1.sql.tmp').write_bytes('contenido sintético de unidad'.encode())
            final = reserva.publicar(directorio)
            self.assertEqual(final.read_bytes(), 'contenido sintético de unidad'.encode())
            self.assertFalse((directorio / 'ola1.sql.reserve').exists())
            self.assertEqual(len((directorio / 'evidencia.jsonl').read_text().splitlines()), 3)
            with self.assertRaises(ValueError):
                reserva.publicar(directorio)

    def test_publicacion_no_sobrescribe_y_evidencia_incompleta_no_completa(self):
        reserva = modulo('reservar_respaldo')
        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            raiz = pathlib.Path(temporal).resolve()
            directorio = raiz / 'corte_colision'
            reserva.reservar(directorio, raiz)
            destino = directorio / 'ola1.sql'
            destino.write_bytes(b'archivo previo')
            (directorio / 'ola1.sql.tmp').write_bytes(b'archivo nuevo')
            with self.assertRaises(ValueError):
                reserva.publicar(directorio)
            self.assertEqual(destino.read_bytes(), b'archivo previo')
            self.assertNotIn('publicacion_completada', (directorio / 'evidencia.jsonl').read_text())

        with tempfile.TemporaryDirectory(dir=pathlib.Path(__file__).parent) as temporal:
            raiz = pathlib.Path(temporal).resolve()
            directorio = raiz / 'corte_sin_evidencia'
            reserva.reservar(directorio, raiz)
            (directorio / 'ola1.sql.tmp').write_bytes(b'archivo temporal')
            registrar = reserva.registrar
            def fallar_al_confirmar(ruta, evento):
                if evento['evento'] == 'publicacion_completada':
                    raise OSError('fallo sintético de evidencia')
                registrar(ruta, evento)
            reserva.registrar = fallar_al_confirmar
            try:
                with self.assertRaises(OSError):
                    reserva.publicar(directorio)
            finally:
                reserva.registrar = registrar
            self.assertTrue((directorio / 'ola1.sql').is_file())
            evidencia = (directorio / 'evidencia.jsonl').read_text()
            self.assertIn('publicacion_iniciada', evidencia)
            self.assertNotIn('publicacion_completada', evidencia)

    def test_secretos_sinteticos(self):
        detectar = modulo('escanear_secretos').hay_secreto
        self.assertTrue(detectar(b'-----BEGIN PRIVATE KEY-----\nmarcador'))
        self.assertTrue(detectar(b'token=valor_sintetico_largo_123456789'))
        self.assertFalse(detectar(b'clave_de_prueba=sin_valor'))


if __name__ == '__main__':
    unittest.main()
