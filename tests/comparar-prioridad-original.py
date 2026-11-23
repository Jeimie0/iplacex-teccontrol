import argparse
import csv
from datetime import date
from pathlib import Path


def leer(ruta):
    with Path(ruta).open(encoding='utf-8-sig', newline='') as archivo:
        return list(csv.DictReader(archivo, delimiter='\t'))


def calcular(orden):
    hoy = date.fromisoformat(orden['hoy'])
    ingreso = date.fromisoformat(orden['fecha_ingreso'][:10])
    antiguedad = min(max((hoy - ingreso).days, 0) * 2, 20)
    urgencia = {'Alta': 30, 'Media': 20, 'Baja': 10, 'NULL': 0}[orden['urgencia']]
    entrega = 0
    if orden['fecha_comprometida'] != 'NULL':
        distancia = (date.fromisoformat(orden['fecha_comprometida']) - hoy).days
        for dias, puntos in ((0, 30), (2, 20), (5, 10)):
            if distancia <= dias:
                entrega = puntos
                break
    return urgencia, antiguedad, entrega


def comparar(entradas, salida_sql):
    if len(entradas) != 20 or len({orden['id_solicitud'] for orden in entradas}) != 20:
        raise AssertionError('Se esperan las 20 solicitudes distintas del conjunto académico.')
    esperadas = []
    for orden in entradas:
        if orden['estado'] == 'Entregada':
            continue
        puntos = calcular(orden)
        esperadas.append((orden, puntos, sum(puntos)))
    esperadas.sort(key=lambda item: (-item[2], item[0]['fecha_ingreso'], int(item[0]['id_solicitud'])))
    esperado = [(int(orden['id_solicitud']), total) for orden, _, total in esperadas]
    observado = [(int(fila['id_solicitud']), int(fila['puntuacion'])) for fila in salida_sql]
    if esperado != observado:
        raise AssertionError(f'Orden o puntuación distintos. Esperado: {esperado}. Observado: {observado}')

    print('OK fórmula SQL original frente a cálculo independiente en las 20 solicitudes del conjunto académico.')
    print('Fecha de referencia de MySQL:', entradas[0]['hoy'])
    print(f'{len(esperadas)} solicitudes pendientes y {len(entradas) - len(esperadas)} entregadas excluidas.')
    print('Posición | Solicitud | Urgencia | Puntos urgencia | Antigüedad | Entrega | Total')
    for posicion, (orden, (urgencia, antiguedad, entrega), total) in enumerate(esperadas, 1):
        nombre = orden['urgencia'] if orden['urgencia'] != 'NULL' else 'Pendiente de clasificación'
        print(f"{posicion} | {orden['id_solicitud']} | {nombre} | {urgencia} | {antiguedad} | {entrega} | {total}")
    entregadas = [orden['id_solicitud'] for orden in entradas if orden['estado'] == 'Entregada']
    print('Entregadas excluidas:', ', '.join(entregadas))
    print('OK desempate por ingreso más antiguo e identificador, incluidos casos 2 y 12.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Compara el resultado de la consulta priorizada original de TecControl.')
    parser.add_argument('entradas')
    parser.add_argument('salida_sql')
    opciones = parser.parse_args()
    comparar(leer(opciones.entradas), leer(opciones.salida_sql))
