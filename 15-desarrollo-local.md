# Desarrollo Local

## Objetivo
Definir un entorno de desarrollo consistente, simple y seguro para el proyecto.

## Regla General
- Se recomienda usar Docker Compose para el desarrollo local.
- Se debe usar `pnpm` como package manager para cualquier tooling Node del proyecto.
- No se deben documentar ni promover comandos con `npm` en este repositorio.

## Recomendacion sobre Docker
Docker si vale la pena para este proyecto porque:
- Laravel depende de servicios externos claros: PostgreSQL y Redis.
- Facilita que todos trabajen con la misma version de servicios y configuracion base.
- Reduce errores de entorno entre macOS, Linux y CI.
- Ayuda a preparar un camino ordenado hacia despliegues reproducibles.

## Servicios Minimos Recomendados
- `app`: PHP / Laravel.
- `postgres`: base de datos principal.
- `redis`: colas, cache y scheduler support.
- `mailpit` o equivalente para pruebas de correo si se usa.

## Uso de Node
Node solo debe existir para assets o tooling auxiliar del panel si realmente hace falta.

Si se requiere:
- instalar dependencias con `pnpm install`
- ejecutar scripts con `pnpm run <script>`
- mantener lockfile de `pnpm`

## Criterios de Aceptacion
- Cualquier nuevo miembro del proyecto puede levantar el entorno con pasos claros y repetibles.
- PostgreSQL y Redis quedan disponibles sin configuracion manual compleja del sistema host.
- La documentacion del proyecto usa `pnpm` de forma consistente.
- No se mezcla `npm` con `pnpm` en scripts, docs o ejemplos.
