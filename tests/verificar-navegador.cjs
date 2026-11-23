'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const { chromium } = require(process.env.TEC_PLAYWRIGHT_PATH || 'playwright');

const puerto = Number(process.env.TEC_BROWSER_PORT || 9443);
if (!Number.isInteger(puerto) || puerto < 1 || puerto > 65535) {
    throw new Error('TEC_BROWSER_PORT debe ser un puerto local válido.');
}
const base = 'https://localhost:' + puerto;
const admin = ['admin@teccontrol.example', 'DemoTec-Admin-2026!'];
const clienteA = ['cliente-a@teccontrol.example', 'DemoTec-Cliente-A-2026!'];
const clienteB = ['cliente-b@teccontrol.example', 'DemoTec-Cliente-B-2026!'];

async function capturar(pagina, archivo) {
    await fs.mkdir(archivo.slice(0, archivo.lastIndexOf('/')), { recursive: true });
    await pagina.screenshot({ path: archivo, animations: 'disabled' });
}

async function entrar(navegador, cuenta, ancho = 1440, alto = 950) {
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, ignoreHTTPSErrors: true });
    const pagina = await contexto.newPage();
    assert.equal((await pagina.goto(base + '/ingresar')).status(), 200);
    await pagina.getByLabel('Correo electrónico').fill(cuenta[0]);
    await pagina.getByLabel('Contraseña', { exact: true }).fill(cuenta[1]);
    await pagina.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await pagina.waitForURL(base + (cuenta === admin ? '/administracion' : '/mis-ordenes'));
    return pagina;
}

async function ir(pagina, ruta, estado = 200) {
    assert.equal((await pagina.goto(base + ruta)).status(), estado);
}

async function registrar(pagina, recurso, campos) {
    await ir(pagina, '/administracion/' + recurso + '/nuevo');
    for (const [selector, valor] of Object.entries(campos)) {
        const campo = pagina.locator('#' + selector);
        if (typeof valor === 'string') {
            await campo.fill(valor);
        } else {
            const opciones = await campo.locator('option').allTextContents();
            const texto = opciones.find(opcion => opcion.includes(valor.contiene));
            assert.ok(texto);
            await campo.selectOption({ label: texto });
        }
    }
}

async function verificar(navegador) {
    const anonimo = await navegador.newPage({ viewport: { width: 1440, height: 950 }, ignoreHTTPSErrors: true });
    await ir(anonimo, '/ingresar');
    await capturar(anonimo, 'evidencias/interfaz/05-ingreso-final.png');
    await anonimo.getByLabel('Correo electrónico').fill(admin[0]);
    await anonimo.getByLabel('Contraseña', { exact: true }).fill('Contraseña incorrecta');
    await anonimo.getByRole('button', { name: 'Ingresar', exact: true }).click();
    await anonimo.getByRole('alert').filter({ hasText: 'El correo o la contraseña son incorrectos.' }).waitFor();

    const gestion = await entrar(navegador, admin);
    await gestion.getByRole('heading', { name: 'Panel de administración' }).waitFor();
    assert.equal(await gestion.getByRole('button', { name: 'Menú', exact: true }).isVisible(), false);
    await capturar(gestion, 'evidencias/interfaz/06-dashboard-administrador.png');
    const panelCliente = await entrar(navegador, clienteA);
    await panelCliente.getByRole('heading', { name: 'Mis órdenes', exact: true }).waitFor();
    await capturar(panelCliente, 'evidencias/interfaz/07-dashboard-cliente.png');
    console.log('OK PROV-01 y PROV-02. Ingreso de ambos perfiles, indicadores y credenciales incorrectas.');

    const seguimiento = await entrar(navegador, clienteB);
    await seguimiento.getByRole('link', { name: 'DEMO-020', exact: true }).click();
    const detalleB = new URL(seguimiento.url()).pathname;
    await seguimiento.getByRole('link', { name: 'Abrir historial' }).click();
    assert.equal(await seguimiento.locator('.eventos li').count(), 5);
    await capturar(seguimiento, 'evidencias/interfaz/11-seguimiento-estados.png');
    await ir(panelCliente, detalleB + '/historial', 404);
    await panelCliente.getByRole('heading', { name: 'Contenido no disponible' }).waitFor();
    await capturar(panelCliente, 'evidencias/seguridad/03-rf04-historial-rechazado.png');
    await ir(panelCliente, '/administracion/ordenes', 403);
    await ir(panelCliente, '/perfil');
    await capturar(panelCliente, 'evidencias/interfaz/12-perfil.png');
    console.log('OK PROV-08 a PROV-10. Historial de cinco estados y rechazo de rutas por titular y rol.');

    const sufijo = Date.now().toString().slice(-6);
    const nuevoCliente = 'Cliente de demostración ' + sufijo;
    const nuevoEquipo = 'Modelo de prueba ' + sufijo;
    await registrar(gestion, 'clientes', { nombre: nuevoCliente, correo_contacto: 'interfaz@teccontrol.example', telefono: '+56 2 0000 0020' });
    await gestion.getByRole('button', { name: 'Guardar registro' }).click();
    await gestion.waitForURL(base + '/administracion/clientes');
    await gestion.getByRole('status').filter({ hasText: 'Registro creado correctamente.' }).waitFor();
    await gestion.getByRole('cell', { name: nuevoCliente, exact: true }).waitFor();
    await capturar(gestion, 'evidencias/interfaz/08-cliente-registrado.png');

    await registrar(gestion, 'equipos', { cliente_id: { contiene: nuevoCliente }, tipo: 'Notebook', marca: 'TecControl Demo', modelo: nuevoEquipo, numero_serie: 'SERIE-' + sufijo });
    await gestion.getByRole('button', { name: 'Guardar registro' }).click();
    await gestion.waitForURL(base + '/administracion/equipos');
    await gestion.getByRole('status').filter({ hasText: 'Registro creado correctamente.' }).waitFor();
    await gestion.getByRole('cell', { name: nuevoEquipo, exact: true }).waitFor();
    await capturar(gestion, 'evidencias/interfaz/09-equipo-registrado.png');

    await registrar(gestion, 'ordenes', { equipo_id: { contiene: nuevoEquipo }, falla_reportada: 'El equipo se apaga durante el arranque.' });
    const folio = await gestion.locator('#folio').inputValue();
    await gestion.getByRole('button', { name: 'Guardar registro' }).click();
    await gestion.waitForURL(/\/administracion\/ordenes\/[0-9]+$/);
    await gestion.getByRole('status').filter({ hasText: 'Registro creado correctamente.' }).waitFor();
    assert.match(await gestion.locator('.eventos').innerText(), /Recibida/);
    await gestion.getByLabel('Estado', { exact: true }).selectOption({ label: 'En diagnóstico' });
    await gestion.getByLabel('Diagnóstico').fill('Se inicia la revisión de alimentación.');
    await gestion.getByRole('button', { name: 'Guardar cambios' }).click();
    await gestion.getByRole('status').filter({ hasText: 'Cambios guardados.' }).waitFor();
    assert.equal(await gestion.locator('.eventos li').count(), 2);
    await gestion.getByRole('link', { name: 'Ver detalle' }).click();
    await gestion.getByRole('heading', { name: 'Orden ' + folio }).waitFor();
    await capturar(gestion, 'evidencias/interfaz/10-orden-y-estado-guardados.png');
    console.log('OK PROV-04 a PROV-07. Formularios enviados en Chrome, relación cliente–equipo y cambio de estado persistidos.');

    await gestion.getByRole('button', { name: 'Cerrar sesión' }).click();
    await gestion.waitForURL(base + '/ingresar');
    await gestion.goto(base + '/administracion');
    await gestion.waitForURL(base + '/ingresar');
    console.log('OK PROV-03. Cierre de sesión y redirección al volver a una ruta privada.');

    for (const ancho of [390, 768, 1440]) {
        const adminAncho = await entrar(navegador, admin, ancho, 920);
        if (ancho === 390) {
            const menu = adminAncho.getByRole('button', { name: 'Menú' });
            await menu.click();
            assert.equal(await menu.getAttribute('aria-expanded'), 'true');
            await adminAncho.getByRole('navigation', { name: 'Administración' }).getByRole('link', { name: 'Órdenes', exact: true }).click();
        } else {
            await ir(adminAncho, '/administracion/ordenes');
        }
        await adminAncho.locator('#buscar').fill('DEMO-011');
        await adminAncho.locator('#buscar').press('Tab');
        assert.equal(await adminAncho.evaluate(() => document.activeElement.id), 'filtro');
        assert.notEqual(await adminAncho.locator('#filtro').evaluate(el => getComputedStyle(el).outlineStyle), 'none');
        await adminAncho.getByRole('button', { name: 'Aplicar' }).click();
        await adminAncho.getByRole('cell', { name: 'DEMO-011', exact: true }).waitFor();
        assert.equal(await adminAncho.locator('tbody tr').count(), 1);
        assert.equal(await adminAncho.evaluate(() => document.documentElement.scrollWidth), ancho);
        if (ancho === 390) {
            const accion = await adminAncho.getByRole('link', { name: 'Editar DEMO-011' }).boundingBox();
            assert.ok(accion && accion.x + accion.width <= ancho);
            assert.ok(await adminAncho.locator('.tabla-scroll').evaluate(el => el.scrollWidth === el.clientWidth));
        }
        await capturar(adminAncho, 'evidencias/responsive/02-ordenes-' + ancho + 'px.png');
        const clienteAncho = await entrar(navegador, clienteA, ancho, 920);
        assert.equal(await clienteAncho.evaluate(() => document.documentElement.scrollWidth), ancho);
        await clienteAncho.getByRole('link', { name: 'Mi perfil', exact: true }).click();
        assert.equal(await clienteAncho.getByLabel('Correo electrónico', { exact: true }).count(), 1);
        assert.equal(await clienteAncho.evaluate(() => document.documentElement.scrollWidth), ancho);
        console.log('OK PROV-C06. ' + ancho + ' px, navegación, foco, filtros y tablas.');
    }
}

async function ejecutar() {
    const navegador = await chromium.launch({ executablePath: process.env.TEC_CHROME || undefined, headless: true });
    try {
        await verificar(navegador);
    } finally {
        await navegador.close();
    }
}
ejecutar().catch(error => { console.error(error); process.exitCode = 1; });
