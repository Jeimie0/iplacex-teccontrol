'use strict';

const botonMenu = document.querySelector('.menu-movil');
if (botonMenu) {
    const menu = document.getElementById(botonMenu.getAttribute('aria-controls'));
    botonMenu.hidden = false;
    menu.dataset.plegado = 'true';
    botonMenu.addEventListener('click', () => {
        const abierto = botonMenu.getAttribute('aria-expanded') === 'true';
        botonMenu.setAttribute('aria-expanded', String(!abierto));
        menu.dataset.plegado = String(abierto);
    });
}

const rol = document.getElementById('rol');
const campoCliente = document.querySelector('[data-campo-cliente]');
if (rol && campoCliente) {
    const selectorCliente = campoCliente.querySelector('select');
    const actualizar = () => {
        const asociado = rol.value === 'cliente';
        campoCliente.hidden = !asociado;
        selectorCliente.required = asociado;
        selectorCliente.disabled = !asociado;
    };
    rol.addEventListener('change', actualizar);
    actualizar();
}
