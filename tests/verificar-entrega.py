import argparse
import html
import re
import runpy
import ssl
from pathlib import Path
from urllib.parse import urlencode

seguridad = runpy.run_path(str(Path(__file__).with_name('verificar-seguridad.py')))
Navegador = seguridad['Navegador']
comprobar = seguridad['comprobar']
csrf = seguridad['csrf']
entrar = seguridad['entrar']
redireccion = seguridad['redireccion']
ADMIN = seguridad['ADMIN']
CLIENTE_A = seguridad['CLIENTE_A']
CLIENTE_B = seguridad['CLIENTE_B']


def valor(respuesta, nombre):
    coincidencia = re.search(fr'name="{re.escape(nombre)}"[^>]*value="([^"]*)"', respuesta.texto)
    if coincidencia is None:
        raise AssertionError(f'Falta el campo {nombre}.')
    return html.unescape(coincidencia[1])


def opcion(respuesta, nombre, contiene):
    campo = re.search(fr'<select id="{re.escape(nombre)}".*?</select>', respuesta.texto, re.S)
    if campo:
        for clave, texto in re.findall(r'<option value="([^"]+)"[^>]*>([^<]+)</option>', campo[0]):
            if contiene in html.unescape(texto):
                return clave
    raise AssertionError(f'Falta la opción {contiene} en {nombre}.')


def enviar(navegador, ruta, pantalla, campos):
    return navegador.solicitar(ruta, {'_csrf': csrf(pantalla), **campos})


def consulta(navegador, ruta, **filtros):
    return navegador.solicitar(ruta + '?' + urlencode(filtros))


def enlace_editar(respuesta, recurso, identificador):
    for ruta, etiqueta in re.findall(fr'href="(/administracion/{recurso}/[1-9][0-9]*)">Editar<span[^>]*> ([^<]+)</span>', respuesta.texto):
        if html.unescape(etiqueta) == identificador:
            return ruta
    raise AssertionError(f'Falta el registro de gestión de {recurso}.')


def verificar(contexto, puerto):
    admin = Navegador(puerto, contexto)
    a = Navegador(puerto, contexto)
    b = Navegador(puerto, contexto)
    comprobar(redireccion(entrar(admin, ADMIN), '/administracion'), 'PROV-01 ingreso administrativo')
    comprobar(redireccion(entrar(a, CLIENTE_A), '/mis-ordenes'), 'PROV-01 ingreso de cliente')
    comprobar(redireccion(entrar(b, CLIENTE_B), '/mis-ordenes'), 'Ingreso de segundo titular ficticio')
    panel = admin.solicitar('/administracion')
    comprobar(
        all(etiqueta in panel.texto for etiqueta in ['Órdenes de servicio', 'Clientes registrados', 'Equipos registrados', 'Cuentas activas'])
        and '<strong>20</strong>' in panel.texto,
        'PROV-01 indicadores de administración calculados desde el conjunto inicial',
    )
    propia_b = seguridad['ordenes'](b.solicitar('/mis-ordenes'))['DEMO-011']
    comprobar('<strong>10</strong>' in a.solicitar('/mis-ordenes').texto, 'PROV-01 indicador personal de diez órdenes')
    estado_ajeno = b.solicitar(propia_b + '/historial')
    ruta_admin_ajena = propia_b.replace('/ordenes/', '/administracion/ordenes/')
    comprobar(
        estado_ajeno.estado == 200 and a.solicitar(propia_b + '/historial').estado == 404
        and a.solicitar(propia_b).estado == 404,
        'PROV-08 y PROV-09 historial propio disponible y ajeno aislado por titularidad',
    )
    intento = enviar(a, ruta_admin_ajena, a.solicitar('/mis-ordenes'), {'diagnostico': 'Prueba de permisos'})
    comprobar(
        intento.estado == 403 and a.solicitar('/administracion').estado == 403
        and b.solicitar(propia_b + '/historial').texto == estado_ajeno.texto,
        'PROV-09 y PROV-10 roles comprobados en una escritura real y el historial ajeno se conserva',
    )

    clientes = '/administracion/clientes'
    cliente = {'nombre': 'Cliente QA TecControl', 'correo_contacto': 'qa-contacto@teccontrol.example', 'telefono': '+56 2 0000 0000'}
    pantalla = admin.solicitar(clientes + '/nuevo')
    comprobar(
        admin.solicitar(clientes + '/nuevo', cliente).estado == 403
        and admin.solicitar(clientes + '/nuevo', {**cliente, '_csrf': 'incorrecto'}).estado == 403
        and 'No hay registros que coincidan.' in consulta(admin, clientes, buscar=cliente['nombre']).texto,
        'PROV-C05 la creación rechazada por CSRF no altera MySQL',
    )
    comprobar(
        enviar(admin, clientes + '/nuevo', pantalla, {'nombre': ''}).estado == 422,
        'Formulario de cliente rechaza campos obligatorios ausentes',
    )
    comprobar(
        redireccion(enviar(admin, clientes + '/nuevo', pantalla, cliente), clientes),
        'PROV-04 registro de cliente con CSRF válido',
    )
    resultado = consulta(admin, clientes, buscar=cliente['nombre'])
    editar = enlace_editar(resultado, 'clientes', cliente['nombre'])
    comprobar(cliente['correo_contacto'] in resultado.texto, 'PROV-04 persistencia de cliente y búsqueda real')
    cliente['telefono'] = '+56 2 0000 0001'
    comprobar(
        redireccion(enviar(admin, editar, admin.solicitar(editar), cliente), clientes)
        and cliente['telefono'] in consulta(admin, clientes, buscar=cliente['nombre']).texto,
        'Datos de cliente actualizados y visibles tras recargar',
    )

    equipos = '/administracion/equipos'
    ficha_equipo = admin.solicitar(equipos + '/nuevo')
    cliente_id = opcion(ficha_equipo, 'cliente_id', cliente['nombre'])
    equipo = {'cliente_id': cliente_id, 'tipo': 'Notebook', 'marca': 'TecControl QA', 'modelo': 'QA-Equipo', 'numero_serie': 'S-QA-01'}
    comprobar(
        redireccion(enviar(admin, equipos + '/nuevo', ficha_equipo, equipo), equipos),
        'PROV-05 equipo vinculado al cliente registrado',
    )
    filtro_equipo = consulta(admin, equipos, buscar='QA-Equipo', filtro=cliente_id)
    comprobar(
        cliente['nombre'] in filtro_equipo.texto and 'S-QA-01' in filtro_equipo.texto,
        'PROV-05 relación del equipo persistida y filtro por cliente funcional',
    )
    editar_equipo = enlace_editar(filtro_equipo, 'equipos', 'QA-Equipo')
    equipo['numero_serie'] = 'S-QA-02'
    comprobar(
        redireccion(enviar(admin, editar_equipo, admin.solicitar(editar_equipo), equipo), equipos)
        and 'S-QA-02' in consulta(admin, equipos, buscar='QA-Equipo').texto,
        'Equipo actualizado con el cliente original',
    )

    ruta_orden = '/administracion/ordenes/nuevo'
    ficha_orden = admin.solicitar(ruta_orden)
    folio = valor(ficha_orden, 'folio')
    orden = {'folio': folio, 'equipo_id': opcion(ficha_orden, 'equipo_id', 'QA-Equipo'), 'falla_reportada': 'Falla de diagnóstico QA', 'diagnostico': ''}
    creada = enviar(admin, ruta_orden, ficha_orden, orden)
    editar_orden = creada.cabeceras.get('Location', '')
    comprobar(
        creada.estado == 303 and editar_orden.startswith('/administracion/ordenes/'),
        'PROV-06 creación de orden por el formulario administrativo',
    )
    ficha = admin.solicitar(editar_orden)
    comprobar(
        ficha.estado == 200 and folio in ficha.texto and 'Recibida' in ficha.texto and 'Historial de estados' in ficha.texto,
        'PROV-06 la orden y el evento inicial están disponibles tras recargar',
    )
    comprobar(
        enviar(admin, ruta_orden, ficha_orden, orden).estado == 422,
        'Reenvío de un mismo folio se rechaza por unicidad',
    )
    previo = valor(ficha, 'evento_id')
    cambios = {
        'evento_id': previo, 'falla_reportada': orden['falla_reportada'],
        'diagnostico': 'Revisión <b>QA</b> completada', 'estado_id': opcion(ficha, 'estado_id', 'En diagnóstico'),
    }
    comprobar(
        redireccion(enviar(admin, editar_orden, ficha, cambios), editar_orden),
        'PROV-07 estado y diagnóstico guardados en transacción',
    )
    actualizada = admin.solicitar(editar_orden)
    comprobar(
        valor(actualizada, 'evento_id') != previo
        and 'En diagnóstico' in actualizada.texto and 'Recibida' in actualizada.texto
        and folio in consulta(admin, '/administracion/ordenes', buscar=folio, filtro='diagnostico').texto,
        'PROV-07 nuevo evento, estado anterior y filtro verificados',
    )
    comprobar(
        enviar(admin, editar_orden, ficha, cambios).estado == 422,
        'Una actualización obsoleta se rechaza sin sobrescribir el estado',
    )
    cambios_invalidos = {**cambios, 'evento_id': valor(actualizada, 'evento_id'), 'estado_id': '65000', 'diagnostico': 'No debe persistir QA'}
    comprobar(
        enviar(admin, editar_orden, actualizada, {**cambios_invalidos, 'estado_id': '999999'}).estado == 422,
        'El servidor valida el rango del identificador de estado',
    )
    comprobar(
        enviar(admin, editar_orden, actualizada, cambios_invalidos).estado == 422
        and 'No debe persistir QA' not in admin.solicitar(editar_orden).texto,
        'El rechazo de un estado inválido revierte el diagnóstico de la misma operación',
    )
    detalle = admin.solicitar(editar_orden.removeprefix('/administracion'))
    comprobar('&lt;b&gt;QA&lt;/b&gt;' in detalle.texto and '<b>QA</b>' not in detalle.texto, 'Escape de salida comprobado con un diagnóstico persistido')

    usuarios = '/administracion/usuarios'
    alta = admin.solicitar(usuarios + '/nuevo')
    cuenta = ['qa-usuario@teccontrol.example', 'TecControl-QA-2026!']
    datos = {'nombre': 'Cuenta QA', 'correo': cuenta[0], 'clave': cuenta[1], 'rol': 'cliente', 'cliente_id': cliente_id}
    comprobar(redireccion(enviar(admin, usuarios + '/nuevo', alta, datos), usuarios), 'Alta administrativa de cuenta con titular y contraseña protegida')
    gestion_cuenta = enlace_editar(consulta(admin, usuarios, buscar='Cuenta QA', filtro='cliente'), 'usuarios', datos['nombre'])
    ficha_cuenta = admin.solicitar(gestion_cuenta)
    lector = Navegador(puerto, contexto)
    comprobar(redireccion(entrar(lector, cuenta), '/mis-ordenes'), 'Cuenta recién creada puede iniciar sesión')
    comprobar(
        redireccion(enviar(admin, gestion_cuenta, ficha_cuenta, {**datos, 'clave': '', 'activo': '0'}), usuarios)
        and redireccion(lector.solicitar('/mis-ordenes'), '/ingresar'),
        'Desactivar la cuenta revoca su acceso en una sesión existente',
    )
    comprobar(
        redireccion(enviar(admin, gestion_cuenta, admin.solicitar(gestion_cuenta), {**datos, 'clave': '', 'activo': '1'}), usuarios),
        'La cuenta puede reactivarse sin cambiar su contraseña',
    )
    comprobar(redireccion(entrar(lector, cuenta), '/mis-ordenes'), 'Cuenta reactivada puede ingresar')
    editor_extra = Navegador(puerto, contexto)
    comprobar(redireccion(entrar(editor_extra, cuenta), '/mis-ordenes'), 'Segunda sesión para comprobar el cambio de contraseña')
    perfil = lector.solicitar('/perfil')
    cuenta[0] = 'qa-perfil@teccontrol.example'
    comprobar(
        redireccion(enviar(lector, '/perfil', perfil, {'accion': 'datos', 'nombre': 'Perfil QA', 'correo': cuenta[0], 'id': '1'}), '/perfil')
        and cuenta[0] in lector.solicitar('/perfil').texto and 'Administración de prueba' in admin.solicitar('/administracion').texto,
        'El perfil persiste en la cuenta autenticada y no altera la cuenta indicada por el formulario',
    )
    clave_nueva = 'NuevaClave-Tec-QA-2026!'
    perfil = lector.solicitar('/perfil')
    clave = {'accion': 'contrasena', 'clave_actual': cuenta[1], 'clave_nueva': clave_nueva, 'confirmacion': clave_nueva}
    comprobar(
        enviar(lector, '/perfil', perfil, {**clave, 'clave_actual': 'incorrecta'}).estado == 422,
        'El cambio de contraseña exige la contraseña actual',
    )
    comprobar(
        redireccion(enviar(lector, '/perfil', perfil, clave), '/ingresar')
        and redireccion(editor_extra.solicitar('/mis-ordenes'), '/ingresar')
        and entrar(lector, cuenta).estado == 422
        and redireccion(entrar(lector, (cuenta[0], clave_nueva)), '/mis-ordenes'),
        'El cambio de contraseña revoca sesiones anteriores y exige el nuevo hash',
    )
    orden_propia = lector.solicitar('/mis-ordenes')
    comprobar(
        folio in orden_propia.texto and orden_propia.texto.count('class="tarjeta orden"') == 1
        and lector.solicitar(propia_b + '/historial').estado == 404,
        'El portal de la cuenta nueva contiene la orden de su equipo y protege historiales ajenos',
    )


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Valida los formularios en una copia local aislada con los datos de ejemplo.')
    parser.add_argument('--puerto', type=int, default=9443)
    parser.add_argument('--ca', default='.local/entrega-ca.crt')
    args = parser.parse_args()
    verificar(ssl.create_default_context(cafile=args.ca), args.puerto)
