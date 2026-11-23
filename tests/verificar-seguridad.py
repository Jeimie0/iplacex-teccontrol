import argparse
import re
import secrets
import ssl
import time
from dataclasses import dataclass
from http.client import HTTPConnection, HTTPSConnection, HTTPMessage
from http.cookies import SimpleCookie
from urllib.parse import urlencode


NOMBRE_COOKIE = 'TecControlSesion'
ADMIN = ('admin@teccontrol.example', 'DemoTec-Admin-2026!')
CLIENTE_A = ('cliente-a@teccontrol.example', 'DemoTec-Cliente-A-2026!')
CLIENTE_B = ('cliente-b@teccontrol.example', 'DemoTec-Cliente-B-2026!')


@dataclass
class Respuesta:
    estado: int
    cabeceras: HTTPMessage
    texto: str


class Navegador:
    def __init__(self, puerto, contexto, cookie=None):
        self.puerto = puerto
        self.contexto = contexto
        self.cookie = cookie

    def solicitar(self, ruta, formulario=None):
        conexion = HTTPSConnection('127.0.0.1', self.puerto, context=self.contexto, timeout=10)
        cabeceras = {}
        if self.cookie is not None:
            cabeceras['Cookie'] = f'{NOMBRE_COOKIE}={self.cookie}'
        cuerpo = None
        if formulario is not None:
            cabeceras['Content-Type'] = 'application/x-www-form-urlencoded'
            cuerpo = urlencode(formulario)

        try:
            conexion.request('GET' if formulario is None else 'POST', ruta, cuerpo, cabeceras)
            respuesta = conexion.getresponse()
            resultado = Respuesta(respuesta.status, respuesta.headers, respuesta.read().decode('utf-8'))
        finally:
            conexion.close()

        recibida = cookie_sesion(resultado)
        if recibida is not None:
            self.cookie = None if recibida['max-age'] == '0' else recibida.value or None
        return resultado


def cookie_sesion(respuesta):
    cookies = SimpleCookie()
    for valor in respuesta.cabeceras.get_all('Set-Cookie', []):
        cookies.load(valor)
    return cookies.get(NOMBRE_COOKIE)


def csrf(respuesta):
    resultado = re.search(r'name="_csrf" value="([0-9a-f]{64})"', respuesta.texto)
    if resultado is None:
        raise AssertionError('No se encontró el token de formulario.')
    return resultado[1]


def atributos_seguros(cookie):
    return (
        cookie is not None
        and bool(cookie['secure'])
        and bool(cookie['httponly'])
        and cookie['samesite'].lower() == 'lax'
        and cookie['path'] == '/'
        and not cookie['domain']
    )


def comprobar(condicion, descripcion):
    if not condicion:
        raise AssertionError(descripcion)
    print(f'OK {descripcion}', flush=True)


def formulario_ingreso(cuenta, token):
    correo, clave = cuenta
    return {'correo': correo, 'clave': clave, '_csrf': token}


def entrar(navegador, cuenta):
    formulario = navegador.solicitar('/ingresar')
    return navegador.solicitar('/ingresar', formulario_ingreso(cuenta, csrf(formulario)))


def ordenes(respuesta):
    return {
        folio: ruta for ruta, folio in re.findall(r'href="(/ordenes/[1-9][0-9]*)">(DEMO-\d{3})</a>', respuesta.texto)
    }


def redireccion(respuesta, ruta):
    return respuesta.estado == 303 and respuesta.cabeceras.get('Location') == ruta


def verificar_principal(contexto, puerto, puerto_http):
    http = HTTPConnection('127.0.0.1', puerto_http, timeout=10)
    try:
        http.request('GET', '/ingresar')
        respuesta = http.getresponse()
        comprobar(
            respuesta.status == 308 and respuesta.getheader('Location') == f'https://localhost:{puerto}/ingresar'
            and respuesta.getheader('Set-Cookie') is None,
            'HTTP redirige a HTTPS sin iniciar una sesión sobre HTTP',
        )
    finally:
        http.close()

    anonimo = Navegador(puerto, contexto)
    comprobar(redireccion(anonimo.solicitar('/administracion'), '/ingresar'), 'Una ruta privada requiere sesión')
    pantalla = Navegador(puerto, contexto).solicitar('/ingresar')
    comprobar(
        pantalla.estado == 200 and atributos_seguros(cookie_sesion(pantalla))
        and 'no-store' in pantalla.cabeceras.get('Cache-Control', ''),
        'Cookie de HTTPS con Secure, HttpOnly y SameSite=Lax, y sin caché de sesión',
    )
    cabecera_cookie = next(valor for valor in pantalla.cabeceras.get_all('Set-Cookie') if valor.startswith(NOMBRE_COOKIE + '='))
    print(f'  Set-Cookie: {NOMBRE_COOKIE}=[oculto];{cabecera_cookie.split(";", 1)[1]}')
    token = csrf(anonimo.solicitar('/ingresar'))
    predicho = secrets.token_hex(16)
    sesion_invalida = Navegador(puerto, contexto, predicho)
    comprobar(
        redireccion(sesion_invalida.solicitar('/administracion'), '/ingresar')
        and sesion_invalida.cookie != predicho,
        'Una sesión sin registro previo se reemplaza y permanece anónima',
    )

    for cuenta in ((ADMIN[0], 'clave-incorrecta'), ('sin-cuenta@teccontrol.example', ADMIN[1])):
        rechazo = anonimo.solicitar('/ingresar', formulario_ingreso(cuenta, token))
        comprobar(
            rechazo.estado == 422 and 'El correo o la contraseña son incorrectos.' in rechazo.texto,
            'Credencial inválida rechazada con mensaje genérico',
        )
    comprobacion_texto = anonimo.solicitar('/ingresar', {'correo': '<b>correo</b>', 'clave': 'prueba', '_csrf': token})
    comprobar(
        comprobacion_texto.estado == 422 and '&lt;b&gt;correo&lt;/b&gt;' in comprobacion_texto.texto
        and '<b>correo</b>' not in comprobacion_texto.texto,
        'Validación del correo del lado servidor y escape de salida HTML',
    )
    sin_token = {'correo': ADMIN[0], 'clave': ADMIN[1]}
    comprobar(
        anonimo.solicitar('/ingresar', sin_token).estado == 403
        and anonimo.solicitar('/ingresar', {**sin_token, '_csrf': 'incorrecto'}).estado == 403
        and redireccion(anonimo.solicitar('/administracion'), '/ingresar'),
        'El ingreso rechaza tokens CSRF ausentes o inválidos',
    )

    cookie_publica = anonimo.cookie
    ingreso = anonimo.solicitar('/ingresar', formulario_ingreso(ADMIN, token))
    comprobar(
        redireccion(ingreso, '/administracion') and atributos_seguros(cookie_sesion(ingreso))
        and anonimo.cookie is not None and anonimo.cookie != cookie_publica
        and redireccion(Navegador(puerto, contexto, cookie_publica).solicitar('/administracion'), '/ingresar'),
        'Ingreso de administrador y regeneración del identificador de sesión',
    )
    admin = anonimo
    panel = admin.solicitar('/administracion')
    comprobar(panel.estado == 200 and 'Administración de prueba' in panel.texto, 'Página de administración accesible con su rol')
    comprobar(admin.solicitar('/mis-ordenes').estado == 403, 'La ruta del cliente exige el perfil correspondiente')

    cliente_a = Navegador(puerto, contexto)
    cliente_b = Navegador(puerto, contexto)
    comprobar(
        redireccion(entrar(cliente_a, CLIENTE_A), '/mis-ordenes')
        and redireccion(entrar(cliente_b, CLIENTE_B), '/mis-ordenes'),
        'Ambos clientes ingresan con la cuenta correspondiente',
    )
    lista_a = cliente_a.solicitar('/mis-ordenes')
    lista_b = cliente_b.solicitar('/mis-ordenes')
    ordenes_a, ordenes_b = ordenes(lista_a), ordenes(lista_b)
    comprobar(
        lista_a.estado == lista_b.estado == 200
        and set(ordenes_a) == {f'DEMO-{i:03}' for i in range(1, 11)}
        and set(ordenes_b) == {f'DEMO-{i:03}' for i in range(11, 21)},
        'RF-04: el listado contiene 10 órdenes propias por cliente',
    )
    acceso_a = ordenes_a['DEMO-001']
    acceso_b = ordenes_b['DEMO-011']
    visitante = Navegador(puerto, contexto)
    comprobar(
        redireccion(visitante.solicitar('/mis-ordenes'), '/ingresar')
        and redireccion(visitante.solicitar(acceso_a), '/ingresar'),
        'El listado y el detalle de órdenes requieren autenticación',
    )
    propia_a = cliente_a.solicitar(acceso_a)
    propia_b = cliente_b.solicitar(acceso_b)
    comprobar(
        propia_a.estado == propia_b.estado == 200 and 'DEMO-001' in propia_a.texto and 'DEMO-011' in propia_b.texto,
        'RF-04: ambos clientes pueden abrir una orden propia y ver su estado',
    )
    ajena = cliente_a.solicitar(f'{acceso_b}?cliente_id=2')
    inexistente = cliente_a.solicitar('/ordenes/18446744073709551615')
    comprobar(
        ajena.estado == inexistente.estado == cliente_b.solicitar(acceso_a).estado == 404
        and ajena.texto == inexistente.texto and 'DEMO-011' not in ajena.texto,
        'RF-04: el servidor devuelve 404 para una orden ajena y para una inexistente',
    )
    comprobar(
        cliente_a.solicitar(acceso_b, {'_csrf': csrf(lista_a)}).estado == 405
        and cliente_b.solicitar(acceso_b).texto == propia_b.texto,
        'RF-04: la ruta de detalle solo permite lectura y el registro ajeno se conserva',
    )
    comprobar(
        cliente_a.solicitar('/administracion').estado == 403 and cliente_b.solicitar('/administracion').estado == 403
        and admin.solicitar(acceso_a).estado == admin.solicitar(acceso_b).estado == 200,
        'El servidor limita administración al rol autorizado',
    )

    cookie_activa = admin.cookie
    token_salir = csrf(panel)
    comprobar(
        admin.solicitar('/salir').estado == 405
        and admin.solicitar('/salir', {}).estado == 403
        and admin.solicitar('/salir', {'_csrf': 'incorrecto'}).estado == 403
        and admin.solicitar('/administracion').estado == 200,
        'El cierre de sesión exige POST y un token CSRF válido',
    )
    salida = admin.solicitar('/salir', {'_csrf': token_salir})
    cookie_borrada = cookie_sesion(salida)
    comprobar(
        redireccion(salida, '/ingresar') and atributos_seguros(cookie_borrada)
        and cookie_borrada['max-age'] == '0' and admin.cookie is None
        and redireccion(admin.solicitar('/administracion'), '/ingresar')
        and redireccion(Navegador(puerto, contexto, cookie_activa).solicitar('/administracion'), '/ingresar'),
        'El cierre borra la cookie e invalida la sesión en el servidor',
    )


def verificar_expiracion(contexto, puerto):
    # Instancia auxiliar configurada con 3 segundos de inactividad y 8 de duración absoluta.
    inactivo = Navegador(puerto, contexto)
    comprobar(redireccion(entrar(inactivo, CLIENTE_A), '/mis-ordenes'), 'La instancia de expiración permite iniciar sesión')
    time.sleep(4)
    comprobar(redireccion(inactivo.solicitar('/mis-ordenes'), '/ingresar'), 'La sesión vence por inactividad')

    activo = Navegador(puerto, contexto)
    comprobar(redireccion(entrar(activo, CLIENTE_A), '/mis-ordenes'), 'Nueva sesión para verificar duración absoluta')
    inicio = ultimo_acceso = time.monotonic()
    lecturas = 0
    while time.monotonic() - inicio < 10:
        respuesta = activo.solicitar('/mis-ordenes')
        ahora = time.monotonic()
        if redireccion(respuesta, '/ingresar'):
            comprobar(
                lecturas >= 2 and ahora - ultimo_acceso < 3 and ahora - inicio >= 6,
                'La duración absoluta termina una sesión activa antes de que venza por inactividad',
            )
            return
        if respuesta.estado != 200:
            raise AssertionError(f'Respuesta inesperada durante la prueba de expiración: {respuesta.estado}.')
        lecturas += 1
        ultimo_acceso = ahora
        time.sleep(0.5)
    raise AssertionError('La sesión activa superó su duración absoluta.')


if __name__ == '__main__':
    argumentos = argparse.ArgumentParser(description='Verifica TecControl únicamente en localhost.')
    argumentos.add_argument('--ca', default='.local/localhost-ca.crt')
    argumentos.add_argument('--puerto', type=int, default=8443)
    argumentos.add_argument('--puerto-http', type=int, default=8080)
    argumentos.add_argument('--puerto-expiracion', type=int)
    opciones = argumentos.parse_args()
    tls = ssl.create_default_context(cafile=opciones.ca)
    verificar_principal(tls, opciones.puerto, opciones.puerto_http)
    if opciones.puerto_expiracion is not None:
        verificar_expiracion(tls, opciones.puerto_expiracion)
