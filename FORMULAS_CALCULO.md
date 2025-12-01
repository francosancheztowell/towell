# FÓRMULAS DE CÁLCULO - ReqProgramaTejidoObserver

## Variables de Entrada:
- `NoTiras` = número de tiras
- `Total` = total del modelo codificado (de ReqModelosCodificados)
- `Luchaje` = luchaje del modelo
- `Repeticiones` = repeticiones del modelo (si es 0, se usa 1)
- `VelocidadSTD` = velocidad estándar
- `EficienciaSTD` = eficiencia estándar (si > 1, se divide entre 100)
- `Cantidad` = TotalPedido o SaldoPedido o Produccion
- `PesoCrudo` = peso crudo
- `LargoToalla` = largo de la toalla
- `AnchoToalla` = ancho de la toalla
- `FechaInicio` = fecha de inicio
- `FechaFinal` = fecha final

---

## FÓRMULA 1: StdToaHra

**Fórmula:**
```
parte1 = Total / 1
parte2 = ((Luchaje * 0.5) / 0.0254) / Repeticiones
  (NOTA: Si Repeticiones = 0, usar Repeticiones = 1)

denominador = (parte1 + parte2) / VelocidadSTD

StdToaHra = (NoTiras * 60) / denominador
```

**Ejemplo con valores:**
- NoTiras = 3
- Total = 1660
- Luchaje = 25
- Repeticiones = 0 (usar 1)
- VelocidadSTD = 500

```
parte1 = 1660 / 1 = 1660
parte2 = ((25 * 0.5) / 0.0254) / 1 = (12.5 / 0.0254) / 1 = 492.1259842519685 / 1 = 492.1259842519685
denominador = (1660 + 492.1259842519685) / 500 = 2152.1259842519685 / 500 = 4.304251968503937
StdToaHra = (3 * 60) / 4.304251968503937 = 180 / 4.304251968503937 = 41.81911312746963
```

---

## FÓRMULA 2: StdDia

**Fórmula:**
```
StdDia = StdToaHra * EficienciaSTD * 24
```

**Ejemplo:**
- StdToaHra = 41.81911312746963
- EficienciaSTD = 0.8

```
StdDia = 41.81911312746963 * 0.8 * 24 = 802.9269720474169
```

---

## FÓRMULA 3: HorasProd

**Fórmula:**
```
HorasProd = Cantidad / (StdToaHra * EficienciaSTD)
```

**Ejemplo:**
- Cantidad = 4300
- StdToaHra = 41.81911312746963
- EficienciaSTD = 0.8

```
HorasProd = 4300 / (41.81911312746963 * 0.8) = 4300 / 33.455290501975704 = 128.5297462817148
```

---

## FÓRMULA 4: ProdKgDia

**Fórmula:**
```
ProdKgDia = (StdDia * PesoCrudo) / 1000
```

**Ejemplo:**
- StdDia = 802.9269720474169
- PesoCrudo = 330 (ejemplo)

```
ProdKgDia = (802.9269720474169 * 330) / 1000 = 264965.9007756476 / 1000 = 264.9659007756476
```

---

## FÓRMULA 5: DiasJornada

**Fórmula:**
```
DiasJornada = HorasProd / 24
```

**Ejemplo:**
- HorasProd = 128.5297462817148

```
DiasJornada = 128.5297462817148 / 24 = 5.355406095071451
```

---

## FÓRMULA 6: DiasEficiencia

**Fórmula:**
```
diffSegundos = abs(FechaFinal - FechaInicio) en segundos
diffDias = diffSegundos / (60 * 60 * 24)

DiasEficiencia = diffDias (redondeado a 2 decimales)
```

---

## FÓRMULA 7: StdHrsEfect

**Fórmula:**
```
StdHrsEfect = (Cantidad / DiasEficiencia) / 24
```

**Ejemplo:**
- Cantidad = 4300
- DiasEficiencia = 5.2

```
StdHrsEfect = (4300 / 5.2) / 24 = 827.6923076923077 / 24 = 34.48717948717949
```

---

## FÓRMULA 8: ProdKgDia2

**Fórmula:**
```
ProdKgDia2 = ((PesoCrudo * StdHrsEfect) * 24) / 1000
```

**Ejemplo:**
- PesoCrudo = 330
- StdHrsEfect = 34.48717948717949

```
ProdKgDia2 = ((330 * 34.48717948717949) * 24) / 1000 = (11380.76923076923 * 24) / 1000 = 273138.4615384615 / 1000 = 273.1384615384615
```

---

## FÓRMULA 9: PesoGRM2

**Fórmula:**
```
PesoGRM2 = (PesoCrudo * 10000) / (LargoToalla * AnchoToalla)
```
(Redondeado a 2 decimales)

---

## NOTAS IMPORTANTES:

1. **Si Repeticiones = 0**: Se usa Repeticiones = 1 para el cálculo de StdToaHra
2. **Si EficienciaSTD > 1**: Se divide entre 100 (ej: 80 → 0.8)
3. **Todos los valores se guardan con TODOS los decimales posibles** (sin redondear, excepto PesoGRM2 y DiasEficiencia)
4. **StdToaHra se usa en cálculos posteriores**: Se usa el valor recién calculado si existe, de lo contrario el valor del modelo

---

## ORDEN DE CÁLCULO:

1. Primero: **StdToaHra** (depende de VelocidadSTD)
2. Segundo: **StdDia** (depende de StdToaHra y EficienciaSTD)
3. Tercero: **HorasProd** (depende de StdToaHra y EficienciaSTD)
4. Cuarto: **ProdKgDia** (depende de StdDia)
5. Quinto: **DiasJornada** (depende de HorasProd)
6. Sexto: **StdHrsEfect** (depende de DiasEficiencia)
7. Séptimo: **ProdKgDia2** (depende de StdHrsEfect)

