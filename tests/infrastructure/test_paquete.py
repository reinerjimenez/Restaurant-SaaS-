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
            for comentario in (b'-- instruccion\n', b'/* instruccion */', b'# instruccion\n'):
                alterado = contenido + comentario
                archivo.write_bytes(alterado)
                hoja.write_text(json.dumps(datos | {'tamano': len(alterado),
                    'sha256': hashlib.sha256(alterado).hexdigest()}), encoding='utf-8')
                self.assertFalse(validar(archivo, hoja))

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
            self.assertEqual(len((directorio / 'evidencia.jsonl').read_text().splitlines()), 2)
            with self.assertRaises(ValueError):
                reserva.publicar(directorio)

    def test_secretos_sinteticos(self):
        detectar = modulo('escanear_secretos').hay_secreto
        self.assertTrue(detectar(b'-----BEGIN PRIVATE KEY-----\nmarcador'))
        self.assertTrue(detectar(b'token=valor_sintetico_largo_123456789'))
        self.assertFalse(detectar(b'clave_de_prueba=sin_valor'))


if __name__ == '__main__':
    unittest.main()
